<?php
declare(strict_types=1);

/*
 * Liefert eine Vorlage ohne Foto als PNG – für das Vorschaubild beim Teilen (og:image) und
 * zur Sichtprüfung des Renderers am Server. Ergebnisse werden in var/ zwischengespeichert.
 */

define('GOG', 1);
require __DIR__ . '/lib/bootstrap.php';

ini_set('display_errors', '0');

try {
    $n = filter_var(
        $_GET['template'] ?? 1,
        FILTER_VALIDATE_INT,
        ['options' => ['min_range' => 1, 'max_range' => 6]]
    );
    if ($n === false || $n === null) {
        gog_fail(400, 'bad_template');
    }

    // Nur im Entwicklungsmodus darf das Backend per Adresse gewählt werden.
    $prefer = null;
    if (gog_config('dev') && isset($_GET['backend'])) {
        $prefer = (string) $_GET['backend'];
    }

    // Cache-Schlüssel aus allem, was das Bild beeinflusst.
    $stamp = 0;
    foreach ([
        GOG_ROOT . '/templates.php',
        GOG_ROOT . '/config.php',
        GOG_ROOT . '/lib/Renderer.php',
        GOG_ROOT . '/lib/GdBackend.php',
        GOG_ROOT . '/lib/ImagickBackend.php',
        (string) gog_config('logo'),
        gog_font_path(800),
    ] as $f) {
        $stamp = max($stamp, is_file($f) ? (int) filemtime($f) : 0);
    }
    $backend = gog_backend_name($prefer);
    $etag = '"' . substr(md5("{$n}|{$stamp}|{$backend}"), 0, 16) . '"';

    if (trim((string) ($_SERVER['HTTP_IF_NONE_MATCH'] ?? '')) === $etag) {
        http_response_code(304);
        header('ETag: ' . $etag);
        header('Cache-Control: public, max-age=86400');
        exit;
    }

    $cache = null;
    if ($prefer === null) {
        try {
            $cache = gog_var_dir('preview') . "/{$n}-{$stamp}-{$backend}.png";
        } catch (Throwable $e) {
            $cache = null; // ohne beschreibbares var/ wird eben jedes Mal gerendert
        }
    }

    $png = ($cache !== null && is_file($cache)) ? (string) file_get_contents($cache) : '';
    if ($png === '') {
        $png = (new GogRenderer($prefer))->render($n, null)->png();
        if ($cache !== null) {
            @file_put_contents($cache, $png, LOCK_EX);
            // ältere Stände desselben Motivs aufräumen
            foreach (glob(dirname($cache) . "/{$n}-*.png") ?: [] as $old) {
                if ($old !== $cache) {
                    @unlink($old);
                }
            }
        }
    }

    gog_send_png(
        $png,
        sprintf('wir-vaeter-vorlage-%d.png', $n),
        false,
        ['ETag: ' . $etag, 'Cache-Control: public, max-age=86400']
    );
} catch (Throwable $e) {
    error_log('gog preview: ' . $e->getMessage());
    gog_fail(500, 'server');
}
