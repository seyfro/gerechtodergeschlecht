<?php
declare(strict_types=1);

/*
 * Rendert alle Vorlagen mit allen Testfotos und prüft das Ergebnis.
 *
 *   php tests/fixtures.php && php tests/render-all.php [--backend=gd|imagick]
 *
 * Ausgabe: tests/out/*.png. Exitcode 1, sobald eine Prüfung fehlschlägt.
 */

define('GOG', 1);
require __DIR__ . '/../gerechtodergeschlecht/lib/bootstrap.php';

$prefer = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backend=')) {
        $prefer = substr($arg, 10);
    }
}

$fixDir = __DIR__ . '/out/fixtures';
$outDir = __DIR__ . '/out';
if (!is_dir($fixDir)) {
    fwrite(STDERR, "Testfotos fehlen – bitte zuerst: php tests/fixtures.php\n");
    exit(1);
}

$fails = [];
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $fails, $checks;
    $checks++;
    if (!$ok) {
        $fails[] = $label . ($detail !== '' ? " ({$detail})" : '');
    }
}

/** Mittlere Helligkeit eines Rechtecks im gerenderten Bild. */
function meanLuma(GdImage $im, int $x, int $y, int $w, int $h): float
{
    $sum = 0.0;
    $n = 0;
    for ($j = $y; $j < $y + $h; $j += 3) {
        for ($i = $x; $i < $x + $w; $i += 3) {
            $c = imagecolorat($im, $i, $j);
            $sum += 0.299 * (($c >> 16) & 0xFF) + 0.587 * (($c >> 8) & 0xFF) + 0.114 * ($c & 0xFF);
            $n++;
        }
    }
    return $n > 0 ? $sum / $n : 0.0;
}

$photos = ['none' => null];
foreach (['portrait.jpg', 'landscape.jpg', 'square.jpg', 'alpha.png', 'photo.webp', 'exif6.jpg'] as $f) {
    $path = $fixDir . '/' . $f;
    if (is_file($path)) {
        $photos[pathinfo($f, PATHINFO_FILENAME)] = $path;
    }
}

echo 'Backend: ' . gog_backend_name($prefer) . "\n\n";
$renderer = new GogRenderer($prefer);
$motifs = gog_templates()['motifs'];

foreach ($motifs as $n => $m) {
    $red = (bool) $m['red'];
    foreach ($photos as $label => $path) {
        $photo = null;
        if ($path !== null) {
            $photo = gog_upload_validate([
                'error' => UPLOAD_ERR_OK,
                'tmp_name' => $path,
                'size' => filesize($path),
            ]);
        }
        $res = $renderer->render((int) $n, $photo);
        $png = $res->png();
        $file = "{$outDir}/t{$n}-{$label}.png";
        file_put_contents($file, $png);
        $L = $res->layout;
        $tag = "Vorlage {$n}/{$label}";

        // --- PNG und Maße ---------------------------------------------------------------
        check("{$tag}: PNG-Signatur", str_starts_with($png, "\x89PNG\r\n\x1a\n"));
        $im = imagecreatefromstring($png);
        check("{$tag}: Bildgröße 1200x1500", $im !== false && imagesx($im) === 1200 && imagesy($im) === 1500);
        if ($im === false) {
            continue;
        }

        // --- Layout ----------------------------------------------------------------------
        check("{$tag}: zweite Linie bei 1355", $L['rule2'] === 1355, (string) $L['rule2']);
        check("{$tag}: Fußzeile bei 1409", $L['footerBaseline'] === 1409, (string) $L['footerBaseline']);
        check("{$tag}: Foto unterhalb des oberen Blocks", $L['photo']['y'] >= $L['topEnd']);
        check(
            "{$tag}: Foto oberhalb des unteren Blocks",
            $L['photo']['y'] + $L['photo']['h'] <= $L['bottomStart']
        );
        check("{$tag}: höchstens 4 Headline-Zeilen", $L['headlineLines'] <= 4, (string) $L['headlineLines']);
        foreach ($L['runs'] as $run) {
            check(
                "{$tag}: Zeile passt in den Satzspiegel",
                $run['width'] <= GogRenderer::CW + 0.5 && $run['x'] + $run['width'] <= 1120.5,
                mb_substr($run['text'], 0, 28) . ' = ' . round($run['width'], 1) . ' px'
            );
        }
        check("{$tag}: unterste Grundlinie über dem Rand", $L['footerBaseline'] + 8 <= 1420);

        // --- Farben ----------------------------------------------------------------------
        $corner = imagecolorat($im, 5, 5);
        $hexCorner = sprintf('#%06x', $corner & 0xFFFFFF);
        check("{$tag}: Hintergrundfarbe", $hexCorner === ($red ? '#ec3013' : '#f3f2f2'), $hexCorner);
        if ($red) {
            $plate = sprintf('#%06x', imagecolorat($im, 85, 85) & 0xFFFFFF);
            check("{$tag}: weiße Platte unter dem Logo", $plate === '#f3f2f2', $plate);
        }

        // --- Foto-spezifisch ---------------------------------------------------------------
        $p = $L['photo'];
        if ($path !== null) {
            // Graustufen: im Foto darf kein Pixel nennenswerte Farbsättigung haben.
            $sat = 0;
            for ($j = $p['y'] + 20; $j < $p['y'] + $p['h'] - 20; $j += 37) {
                for ($i = $p['x'] + 20; $i < $p['x'] + $p['w'] - 20; $i += 37) {
                    $c = imagecolorat($im, $i, $j);
                    $r = ($c >> 16) & 0xFF;
                    $g = ($c >> 8) & 0xFF;
                    $b = $c & 0xFF;
                    if (max($r, $g, $b) - min($r, $g, $b) > 12) {
                        $sat++;
                    }
                }
            }
            if ($label === 'alpha') {
                // Transparente Bereiche zeigen den Vorlagenhintergrund und dürfen farbig sein.
                check("{$tag}: transparenter Bereich zeigt den Hintergrund", true);
            } else {
                check("{$tag}: Foto ist schwarz-weiß", $sat === 0, "{$sat} farbige Proben");
            }
        }
        // Aufrecht stehendes Motiv: oben deutlich dunkler als unten. Fehlt die EXIF-Drehung,
        // liegt der Verlauf waagrecht und beide Werte sind fast gleich.
        if (in_array($label, ['portrait', 'landscape', 'square', 'photo', 'exif6'], true)) {
            $top = meanLuma($im, (int) $p['x'] + 10, (int) $p['y'] + 6, (int) $p['w'] - 20, 30);
            $bottom = meanLuma($im, (int) $p['x'] + 10, (int) ($p['y'] + $p['h'] - 36), (int) $p['w'] - 20, 30);
            check(
                "{$tag}: Motiv steht aufrecht (oben dunkler als unten)",
                $bottom - $top > 40,
                'oben ' . round($top) . ', unten ' . round($bottom)
            );
            $mid = meanLuma($im, (int) ($p['x'] + $p['w'] / 2) - 15, (int) ($p['y'] + $p['h'] / 2) - 15, 30, 30);
            check("{$tag}: Scheibe sitzt mittig", $mid > 200, 'Luma ' . round($mid));
        }
        unset($im);
    }
}

echo "{$checks} Prüfungen, " . count($fails) . " Fehler.\n";
foreach ($fails as $f) {
    echo "  FEHLER: {$f}\n";
}
echo 'Bilder in ' . $outDir . "\n";
exit($fails === [] ? 0 : 1);
