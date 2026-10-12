<?php
declare(strict_types=1);

/*
 * Gemeinsames Interface der Bild-Backends (GD, Imagick). Der Renderer kennt nur dieses
 * Interface; Koordinaten sind Pixel auf der 1200 × 1500-Fläche, y-Werte von Text sind
 * Baselines (wie Canvas textBaseline = 'alphabetic').
 *
 * Textbreite ist in beiden Backends gleich definiert:
 *   Advance-Breite der Zeichenfolge + Letterspacing × Zeichenzahl
 * (Blink hängt Letterspacing auch ans letzte Zeichen; Hauptsache Messen und Zeichnen
 * verwenden dieselbe Definition.)
 */
interface ImageBackend
{
    public static function available(): bool;

    public function __construct(int $w, int $h, string $bgHex);

    public function fillRect(int $x, int $y, int $w, int $h, string $hex): void;

    public function textWidth(string $text, string $font, float $px, float $ls): float;

    /** Zeichnet Text und liefert die gemessene Breite. $align 'left' | 'right'. */
    public function drawText(
        string $text,
        float $x,
        float $baseline,
        string $font,
        float $px,
        float $ls,
        string $hex,
        string $align = 'left'
    ): float;

    /** Zeichnet das Logo mit Höhe $h (Breite proportional) und liefert die gezeichnete Breite. */
    public function drawLogo(string $pngPath, float $x, float $y, float $h, string $underHex): float;

    /**
     * Setzt das Foto (Array aus gog_upload_validate: path, mime, w, h, orientation) als
     * Cover-Crop in das Rechteck, Schwarz-Weiß mit Kontrastfaktor (1.08 = CSS contrast(1.08)).
     */
    public function drawPhoto(array $photo, int $x, int $y, int $w, int $h, float $contrast): void;

    /** PNG-Binärdaten des fertigen Bildes. */
    public function png(int $level): string;
}

/** Pfad zur statischen Archivo-TTF für ein Gewicht (400, 600, 800). */
function gog_font_path(int $weight): string
{
    $map = [400 => 'Archivo-Regular.ttf', 600 => 'Archivo-SemiBold.ttf', 800 => 'Archivo-ExtraBold.ttf'];
    if (!isset($map[$weight])) {
        throw new InvalidArgumentException("Kein Schnitt für Gewicht {$weight}");
    }
    $path = rtrim((string) gog_config('font_dir'), '/\\') . '/' . $map[$weight];
    if (!is_file($path)) {
        throw new RuntimeException("Schriftdatei fehlt: {$path}");
    }
    return $path;
}

/** '#rrggbb' → [r, g, b]. */
function gog_hex_rgb(string $hex): array
{
    $hex = ltrim($hex, '#');
    if (strlen($hex) === 3) {
        $hex = $hex[0] . $hex[0] . $hex[1] . $hex[1] . $hex[2] . $hex[2];
    }
    if (!preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
        throw new InvalidArgumentException("Ungültige Farbe: {$hex}");
    }
    return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
}

/** Ermittelt das zu verwendende Backend ('gd' | 'imagick') laut Config bzw. Wunsch. */
function gog_backend_name(?string $prefer = null): string
{
    $want = $prefer ?? (string) gog_config('backend');
    if ($want === 'imagick') {
        if (!ImagickBackend::available()) {
            throw new RuntimeException('Imagick ist nicht verfügbar');
        }
        return 'imagick';
    }
    if ($want === 'gd') {
        if (!GdBackend::available()) {
            throw new RuntimeException('GD mit FreeType ist nicht verfügbar');
        }
        return 'gd';
    }
    if (ImagickBackend::available()) {
        return 'imagick';
    }
    if (GdBackend::available()) {
        return 'gd';
    }
    throw new RuntimeException('Weder Imagick noch GD verfügbar');
}

function gog_backend_create(int $w, int $h, string $bgHex, ?string $prefer = null): ImageBackend
{
    return gog_backend_name($prefer) === 'imagick'
        ? new ImagickBackend($w, $h, $bgHex)
        : new GdBackend($w, $h, $bgHex);
}
