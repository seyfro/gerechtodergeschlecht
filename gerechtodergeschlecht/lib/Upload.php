<?php
declare(strict_types=1);

/** Fehler bei der Upload-Prüfung; trägt HTTP-Status und den Textschlüssel aus texts.php. */
final class GogUploadException extends RuntimeException
{
    public function __construct(
        public readonly int $status,
        public readonly string $errorCode,
        string $detail = ''
    ) {
        parent::__construct($detail !== '' ? $detail : $errorCode);
    }
}

/*
 * Prüfung des hochgeladenen Fotos. Nichts wird dauerhaft gespeichert: Es bleibt bei PHPs
 * temporärer Upload-Datei, die generate.php nach der Antwort löscht.
 */

/**
 * @param array<string,mixed>|null $file Eintrag aus $_FILES
 * @return array{path:string,mime:string,w:int,h:int,orientation:int}
 */
function gog_upload_validate(?array $file): array
{
    $cfg = gog_config();

    if ($file === null || !isset($file['error'])) {
        throw new GogUploadException(400, 'no_file');
    }
    $err = (int) $file['error'];
    if ($err === UPLOAD_ERR_NO_FILE) {
        throw new GogUploadException(400, 'no_file');
    }
    if ($err === UPLOAD_ERR_INI_SIZE || $err === UPLOAD_ERR_FORM_SIZE) {
        throw new GogUploadException(413, 'too_large');
    }
    if ($err === UPLOAD_ERR_PARTIAL) {
        throw new GogUploadException(400, 'partial');
    }
    if ($err !== UPLOAD_ERR_OK) {
        throw new GogUploadException(500, 'server', 'UPLOAD_ERR ' . $err);
    }

    $path = (string) ($file['tmp_name'] ?? '');
    // In Tests wird direkt eine Datei übergeben; im Web muss es ein echter Upload sein.
    if ($path === '' || !is_file($path) || (PHP_SAPI !== 'cli' && !is_uploaded_file($path))) {
        throw new GogUploadException(400, 'no_file', 'keine gültige Upload-Datei');
    }

    $size = (int) filesize($path);
    if ($size <= 0) {
        throw new GogUploadException(400, 'unreadable', 'leere Datei');
    }
    if ($size > (int) $cfg['max_upload_bytes']) {
        throw new GogUploadException(413, 'too_large');
    }

    // Der vom Browser gemeldete Typ und die Endung werden bewusst ignoriert.
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    if ($finfo === false) {
        throw new GogUploadException(500, 'server', 'finfo nicht verfügbar');
    }
    $mime = (string) finfo_file($finfo, $path);
    finfo_close($finfo);
    if (!in_array($mime, $cfg['allowed_mime'], true)) {
        throw new GogUploadException(415, 'unsupported_type', $mime);
    }

    $info = @getimagesize($path);
    if ($info === false || !isset($info[0], $info[1])) {
        throw new GogUploadException(400, 'unreadable', 'getimagesize fehlgeschlagen');
    }
    if (isset($info['mime']) && $info['mime'] !== $mime) {
        throw new GogUploadException(415, 'unsupported_type', $mime . ' != ' . $info['mime']);
    }
    $w = (int) $info[0];
    $h = (int) $info[1];
    if ($w < 1 || $h < 1) {
        throw new GogUploadException(400, 'unreadable', 'Maße 0');
    }
    if ($w > (int) $cfg['max_dim'] || $h > (int) $cfg['max_dim'] || $w * $h > (int) $cfg['max_pixels']) {
        throw new GogUploadException(413, 'too_many_pixels', "{$w}x{$h}");
    }

    if (gog_upload_is_truncated($path, $mime)) {
        throw new GogUploadException(400, 'unreadable', 'Datei unvollständig');
    }

    gog_upload_reserve_memory($w, $h, $mime);

    return [
        'path' => $path,
        'mime' => $mime,
        'w' => $w,
        'h' => $h,
        'orientation' => gog_upload_orientation($path, $mime),
    ];
}

/**
 * Erkennt abgebrochene Downloads und beschädigte Dateien an der fehlenden Endmarke.
 *
 * Nötig, weil GD hier nicht hilft: Ein halbes JPEG lädt es klaglos und füllt den Rest grau
 * auf (libjpeg-Warnungen sind über gd.jpeg_ignore_warning standardmäßig stumm). Ohne diese
 * Prüfung bekäme man ein halb graues Bild statt einer Meldung.
 *
 * Die Marke wird in den letzten Bytes gesucht, nicht exakt am Ende erwartet: Manche Kameras
 * und Programme hängen noch Füllbytes an, und die Datei ist damit trotzdem vollständig.
 */
function gog_upload_is_truncated(string $path, string $mime): bool
{
    $size = (int) filesize($path);
    if ($mime === 'image/webp') {
        // RIFF-Kopf: Bytes 4..7 sind die Restlänge nach dem 8 Byte langen Kopf.
        $head = (string) file_get_contents($path, false, null, 0, 12);
        if (strlen($head) < 12 || substr($head, 0, 4) !== 'RIFF') {
            return true;
        }
        $declared = unpack('V', substr($head, 4, 4))[1] + 8;
        return $size < $declared;
    }
    $tailLen = $mime === 'image/png' ? 16 : 64;
    $tail = (string) file_get_contents($path, false, null, max(0, $size - $tailLen));
    $marker = $mime === 'image/png' ? "IEND\xAE\x42\x60\x82" : "\xFF\xD9";
    return !str_contains($tail, $marker);
}

/**
 * Hebt memory_limit an, wenn das Bild sonst nicht in den Speicher passt. GD legt vier Byte
 * je Pixel an; PNG und WebP brauchen zusätzlich einen vollen Zwischenpuffer im Decoder.
 * Reicht es trotzdem nicht, gibt es eine klare 413 statt einer weißen Seite.
 */
function gog_upload_reserve_memory(int $w, int $h, string $mime): void
{
    $need = $w * $h * 4 * ($mime === 'image/jpeg' ? 1 : 2) + 48 * 1024 * 1024;
    $limit = gog_ini_bytes((string) ini_get('memory_limit'));
    if ($limit < 0) {
        return; // unbegrenzt
    }
    $required = memory_get_usage(true) + $need;
    if ($required <= $limit) {
        return;
    }
    $target = (int) ceil($required / (1024 * 1024)) + 32;
    @ini_set('memory_limit', $target . 'M');
    $limit = gog_ini_bytes((string) ini_get('memory_limit'));
    if ($limit >= 0 && memory_get_usage(true) + $need > $limit) {
        throw new GogUploadException(413, 'too_many_pixels', 'memory_limit zu klein');
    }
}

/** "128M" → Bytes; -1 bleibt -1. */
function gog_ini_bytes(string $value): int
{
    $value = trim($value);
    if ($value === '' || $value === '-1') {
        return -1;
    }
    $unit = strtolower(substr($value, -1));
    $num = (int) $value;
    return match ($unit) {
        'g' => $num * 1024 * 1024 * 1024,
        'm' => $num * 1024 * 1024,
        'k' => $num * 1024,
        default => $num,
    };
}

/** EXIF-Orientierung 1..8; nur JPEG trägt sie, Fehler dürfen den Upload nicht kippen. */
function gog_upload_orientation(string $path, string $mime): int
{
    if ($mime !== 'image/jpeg' || !function_exists('exif_read_data')) {
        return 1;
    }
    try {
        $exif = @exif_read_data($path);
    } catch (Throwable $e) {
        return 1;
    }
    $o = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;
    return ($o >= 1 && $o <= 8) ? $o : 1;
}

/**
 * Erkennt einen Request, dessen Body PHP wegen post_max_size verworfen hat: Content-Length
 * ist gesetzt, aber $_POST und $_FILES sind leer. Ohne diese Prüfung sähe das wie ein
 * Formular ganz ohne Felder aus.
 */
function gog_post_body_dropped(): bool
{
    $len = (int) ($_SERVER['CONTENT_LENGTH'] ?? 0);
    return $len > 0 && $_POST === [] && $_FILES === [];
}
