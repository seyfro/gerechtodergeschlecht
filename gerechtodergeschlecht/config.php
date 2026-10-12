<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

/*
 * Konfiguration des Kampagnen-One-Pagers.
 * Werte, die am Host abweichen (z. B. backend, trusted_proxy_header), gehören in
 * config.local.php (gleiche Struktur, nur die abweichenden Schlüssel; nicht im Repo).
 */
return [
    // 'auto' nimmt Imagick, wenn die Extension da ist, sonst GD. 'gd' | 'imagick' erzwingen.
    'backend' => 'auto',

    // DEV schaltet Diagnose-Optionen frei (preview.php?backend=…, Fehlerdetails im Log). Nie am Host.
    'dev' => false,

    // Upload-Grenzen (README: 12 MB, 6000 × 6000 px). max_pixels schützt den Speicher zusätzlich.
    'max_upload_bytes' => 12 * 1024 * 1024,
    'max_dim' => 6000,
    'max_pixels' => 36_000_000,
    'allowed_mime' => ['image/jpeg', 'image/png', 'image/webp'],

    // Kontrast wie CSS contrast(1.08) auf dem Schwarz-Weiß-Foto. Die Backends rechnen das
    // jeweils in ihre eigene Skala um (GD: -4, Imagick: levelImage).
    'photo_contrast' => 1.08,
    'png_level' => 6,

    // Generierungen pro IP und Fenster (Sekunden).
    'rate_limit' => ['limit' => 10, 'window' => 60],

    // Gültigkeit des Formular-Tokens in Sekunden (Seite darf so lange offen bleiben).
    'token_ttl' => 86400,

    // Header mit der echten Client-IP hinter einem Proxy, z. B. 'HTTP_X_FORWARDED_FOR'.
    // Nur setzen, wenn der Hoster garantiert, dass der Header von außen nicht gefälscht werden kann.
    'trusted_proxy_header' => null,

    // GOG_VAR_DIR erlaubt den Tests einen frischen Laufzeitordner (Rate-Limit, Secret).
    'var_dir' => getenv('GOG_VAR_DIR') ?: __DIR__ . '/var',
    'font_dir' => __DIR__ . '/assets/fonts',
    'logo' => __DIR__ . '/assets/wir-vaeter-logo.png',
    'download_name' => 'wir-vaeter-gerecht-oder-geschlecht-%d.png',
];
