<?php
declare(strict_types=1);

/*
 * Nimmt Vorlage und Foto entgegen und liefert das fertige PNG als Download.
 *
 * Ohne JavaScript ist die Antwort direkt die Datei; mit JavaScript (app.js) kommt bei einem
 * Fehler JSON zurück. Das hochgeladene Foto wird nie gespeichert – nur PHPs temporäre Datei,
 * die am Ende gelöscht wird.
 */

define('GOG', 1);
require __DIR__ . '/lib/bootstrap.php';

ob_start();
ini_set('display_errors', '0');

// Warnungen aus GD & Co. sollen als Ausnahme im Fehlerpfad landen, nicht im PNG.
set_error_handler(static function (int $no, string $str, string $file = '', int $line = 0): bool {
    if ((error_reporting() & $no) === 0) {
        return true;
    }
    throw new ErrorException($str, 0, $no, $file, $line);
});

// Fataler Fehler (z. B. Speicher erschöpft): trotzdem eine saubere Antwort geben.
register_shutdown_function(static function (): void {
    $err = error_get_last();
    if ($err !== null && in_array($err['type'], [E_ERROR, E_PARSE, E_CORE_ERROR, E_COMPILE_ERROR], true)) {
        error_log('gog generate: ' . $err['message'] . ' in ' . $err['file'] . ':' . $err['line']);
        if (!headers_sent()) {
            gog_fail(500, 'server');
        }
    }
});

$template = null;

try {
    // 1 – nur POST
    if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
        gog_fail(405, 'method');
    }

    // 2 – Body von PHP verworfen (post_max_size)
    if (gog_post_body_dropped()) {
        gog_fail(413, 'too_large');
    }

    // 3 – Formular-Token
    if (!gog_form_token_valid($_POST['csrf'] ?? null)) {
        gog_fail(400, 'token');
    }

    // 4 – Vorlage
    $template = filter_var(
        $_POST['template'] ?? null,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 6]]
    );
    if ($template === false || $template === null) {
        $template = null;
        gog_fail(400, 'bad_template');
    }

    // 5 – Rate-Limit (zählt jeden ernst gemeinten Versuch)
    $rl = gog_config('rate_limit');
    $wait = gog_rate_limit(gog_client_ip(), (int) $rl['limit'], (int) $rl['window']);
    if ($wait > 0) {
        gog_fail(429, 'rate_limited', $template, $wait);
    }

    // 6 – Foto prüfen
    $photo = gog_upload_validate($_FILES['photo'] ?? null);

    try {
        // 7 – rendern
        $png = (new GogRenderer())->render($template, $photo)->png();
    } finally {
        // Die temporäre Upload-Datei sofort entfernen, unabhängig vom Ausgang.
        if (is_file($photo['path'])) {
            @unlink($photo['path']);
        }
    }

    // 8 – ausliefern
    gog_send_png($png, sprintf((string) gog_config('download_name'), $template));
} catch (GogUploadException $e) {
    error_log('gog upload: ' . $e->getMessage());
    gog_fail($e->status, $e->errorCode, $template);
} catch (Throwable $e) {
    error_log('gog generate: ' . $e->getMessage() . ' in ' . $e->getFile() . ':' . $e->getLine());
    gog_fail(500, 'server', $template);
}
