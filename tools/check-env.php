<?php
declare(strict_types=1);

/*
 * Zeigt, ob die Umgebung alles mitbringt, was der Generator braucht.
 *
 * Am Server: die Datei vorübergehend in den Ordner der Seite legen, im Browser aufrufen,
 * Ergebnis lesen – und danach WIEDER LÖSCHEN. Sie verrät sonst unnötig viel über den Host.
 * Auf der Kommandozeile: php tools/check-env.php
 */

define('GOG', 1);
$bootstrap = is_file(__DIR__ . '/../gerechtodergeschlecht/lib/bootstrap.php')
    ? __DIR__ . '/../gerechtodergeschlecht/lib/bootstrap.php'
    : __DIR__ . '/lib/bootstrap.php';
require $bootstrap;

$cli = PHP_SAPI === 'cli';
$rows = [];
$problems = 0;

function row(string $label, bool $ok, string $value, bool $warnOnly = false): void
{
    global $rows, $problems;
    if (!$ok && !$warnOnly) {
        $problems++;
    }
    $rows[] = [$ok ? 'ok' : ($warnOnly ? 'hinweis' : 'FEHLT'), $label, $value];
}

row('PHP-Version (mindestens 8.1)', PHP_VERSION_ID >= 80100, PHP_VERSION);

$gd = GdBackend::available();
$imagick = ImagickBackend::available();
row('Bild-Backend vorhanden', $gd || $imagick, ($gd ? 'GD ' : '') . ($imagick ? 'Imagick' : ''));
if ($gd) {
    $info = gd_info();
    row('GD: FreeType', !empty($info['FreeType Support']), $info['GD Version'] ?? '');
    row('GD: JPEG', !empty($info['JPEG Support']), '');
    row('GD: PNG', !empty($info['PNG Support']), '');
    row('GD: WebP', !empty($info['WebP Support']), '', true);
}
if ($imagick) {
    row('Imagick-Version', true, (new Imagick())->getVersion()['versionString'] ?? '');
}
row('gewähltes Backend', true, (static function () {
    try {
        return gog_backend_name();
    } catch (Throwable $e) {
        return 'keines: ' . $e->getMessage();
    }
})());

foreach (['exif', 'fileinfo', 'mbstring'] as $ext) {
    row("Erweiterung {$ext}", extension_loaded($ext), '');
}

$upload = gog_ini_bytes((string) ini_get('upload_max_filesize'));
$post = gog_ini_bytes((string) ini_get('post_max_size'));
$mem = gog_ini_bytes((string) ini_get('memory_limit'));
$need = (int) gog_config('max_upload_bytes');
row('upload_max_filesize (mind. 12 MB)', $upload >= $need, (string) ini_get('upload_max_filesize'));
row('post_max_size (mind. 13 MB)', $post >= $need + 1024 * 1024, (string) ini_get('post_max_size'));
row('memory_limit (mind. 256 MB)', $mem < 0 || $mem >= 256 * 1024 * 1024, (string) ini_get('memory_limit'), true);
row('max_execution_time', ((int) ini_get('max_execution_time')) === 0 || ((int) ini_get('max_execution_time')) >= 30, (string) ini_get('max_execution_time'), true);

try {
    $dir = gog_var_dir();
    row('Laufzeitordner beschreibbar', is_writable($dir), $dir);
} catch (Throwable $e) {
    row('Laufzeitordner beschreibbar', false, $e->getMessage());
}

foreach ([400, 600, 800] as $weight) {
    try {
        $p = gog_font_path($weight);
        row("Schriftschnitt {$weight}", is_readable($p), basename($p));
    } catch (Throwable $e) {
        row("Schriftschnitt {$weight}", false, $e->getMessage());
    }
}
$logo = (string) gog_config('logo');
$logoInfo = is_file($logo) ? @getimagesize($logo) : false;
row('Logo lesbar', $logoInfo !== false, $logoInfo ? "{$logoInfo[0]}x{$logoInfo[1]}" : $logo);

// Probelauf: eine Vorlage ohne Foto rendern.
$t0 = microtime(true);
try {
    $png = (new GogRenderer())->render(3, null)->png();
    $ms = (int) round((microtime(true) - $t0) * 1000);
    row('Testrendering', str_starts_with($png, "\x89PNG"), strlen($png) . " Bytes in {$ms} ms");
} catch (Throwable $e) {
    row('Testrendering', false, $e->getMessage());
}

if ($cli) {
    foreach ($rows as [$state, $label, $value]) {
        printf("  [%-7s] %-34s %s\n", $state, $label, $value);
    }
    echo "\n" . ($problems === 0 ? "Alles vorhanden.\n" : "{$problems} Problem(e).\n");
    exit($problems === 0 ? 0 : 1);
}

header('Content-Type: text/html; charset=utf-8');
header('X-Robots-Tag: noindex');
echo '<!doctype html><meta charset="utf-8"><title>Umgebung</title>';
echo '<style>body{font:14px/1.6 system-ui,sans-serif;margin:24px}table{border-collapse:collapse}'
    . 'td{padding:3px 10px;border-bottom:1px solid #eee}.bad{color:#b00020;font-weight:700}'
    . '.warn{color:#b45309}.ok{color:#137333}</style>';
echo '<h1>Umgebung</h1><table>';
foreach ($rows as [$state, $label, $value]) {
    $cls = $state === 'ok' ? 'ok' : ($state === 'hinweis' ? 'warn' : 'bad');
    echo '<tr><td class="' . $cls . '">' . e($state) . '</td><td>' . e($label) . '</td><td>' . e($value) . '</td></tr>';
}
echo '</table><p><strong>' . ($problems === 0 ? 'Alles vorhanden.' : $problems . ' Problem(e).')
    . '</strong> Diese Datei nach der Prüfung wieder löschen.</p>';
