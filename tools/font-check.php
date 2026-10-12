<?php
declare(strict_types=1);

/*
 * Prüft die Textmessung und zeigt die Zeilenumbrüche aller Bildtexte.
 *
 *   php tools/font-check.php [--backend=gd|imagick]
 *
 * Erwartung: Die Umbrüche stimmen mit tools/wrap-browser.html überein (dieselben TTFs im
 * Browser). Weicht eine Zeile ab, hilft ein "\n" in templates.php.
 */

define('GOG', 1);
require __DIR__ . '/../gerechtodergeschlecht/lib/bootstrap.php';

$prefer = null;
foreach ($argv as $arg) {
    if (str_starts_with($arg, '--backend=')) {
        $prefer = substr($arg, 10);
    }
}

$backendName = gog_backend_name($prefer);
echo "Backend: {$backendName}\n";
echo 'GD: ' . (GdBackend::available() ? 'ja' : 'nein')
    . ' | Imagick: ' . (ImagickBackend::available() ? 'ja' : 'nein') . "\n\n";

$b = gog_backend_create(10, 10, '#ffffff', $prefer);
$fontHead = gog_font_path(800);
$fontSemi = gog_font_path(600);

// --- 1. Vorschubbreiten müssen sich additiv verhalten -----------------------------------
$fail = 0;
$check = function (string $label, float $got, float $want, float $tol = 1.0) use (&$fail): void {
    $ok = abs($got - $want) <= $tol;
    if (!$ok) {
        $fail++;
    }
    printf("  [%s] %-38s %8.2f  (erwartet %8.2f)\n", $ok ? 'ok' : 'FEHLER', $label, $got, $want);
};

echo "Vorschubbreiten (Archivo ExtraBold 78px):\n";
$advH = $b->textWidth('H', $fontHead, 78, 0.0);
$check('adv("HH") == 2 x adv("H")', $b->textWidth('HH', $fontHead, 78, 0.0), 2 * $advH);
$advSpace = $b->textWidth(' ', $fontHead, 78, 0.0);
$check('adv("H ") == adv("H") + adv(" ")', $b->textWidth('H ', $fontHead, 78, 0.0), $advH + $advSpace);
$check(
    'Letterspacing 5px auf "HHH" = +15',
    $b->textWidth('HHH', $fontHead, 78, 5.0) - $b->textWidth('HHH', $fontHead, 78, 0.0),
    15.0,
    0.01
);
$check(
    'geschuetztes Leerzeichen == normales',
    $b->textWidth("60\u{00A0}%", $fontHead, 78, 0.0),
    $b->textWidth('60 %', $fontHead, 78, 0.0),
    0.01
);

// --- 2. Groessenumrechnung: Versalhoehe skaliert linear ---------------------------------
if ($backendName === 'gd') {
    $capAt78 = capHeight($fontHead, 78);
    $capAt156 = capHeight($fontHead, 156);
    echo "\nVersalhoehe (GD, pt = px * 0.75):\n";
    $check('Versalhoehe 78px', $capAt78, $capAt156 / 2, 1.5);
    printf("  Info: Versalhoehe bei 78px = %d px (%.3f em)\n", $capAt78, $capAt78 / 78);
}

// --- 3. Zeilenumbrueche aller Bildtexte -------------------------------------------------
$tpl = gog_templates();
echo "\nZeilenumbrueche (Umbruchbreite " . GogRenderer::CW . " px):\n";
foreach ($tpl['motifs'] as $n => $m) {
    $lines = gog_wrap(
        fn(string $s): float => $b->textWidth($s, $fontHead, 78, -1.5),
        (string) $m['headline'],
        GogRenderer::CW
    );
    echo "\n  Vorlage {$n} – Headline (ExtraBold 78, ls -1.5), " . count($lines) . " Zeilen:\n";
    foreach ($lines as $line) {
        $w = $b->textWidth($line, $fontHead, 78, -1.5);
        $flagged = $w > GogRenderer::CW ? ' ZU BREIT' : ($w > GogRenderer::CW - 30 ? ' (knapp)' : '');
        printf("    %7.1f px  %s%s\n", $w, str_replace("\u{00A0}", '_', $line), $flagged);
        if ($w > GogRenderer::CW) {
            $fail++;
        }
    }
}

$stLines = gog_wrap(
    fn(string $s): float => $b->textWidth($s, $fontHead, 46, -0.5),
    (string) $tpl['statement'],
    GogRenderer::CW
);
echo "\n  Statement (ExtraBold 46, ls -0.5), " . count($stLines) . " Zeilen:\n";
foreach ($stLines as $line) {
    printf("    %7.1f px  %s\n", $b->textWidth($line, $fontHead, 46, -0.5), $line);
}

$kickerW = $b->textWidth((string) $tpl['kicker'], $fontSemi, 22, 1.8);
$footW = $b->textWidth($tpl['hashtags'][0], $fontSemi, 28, 0.0)
    + 32 + $b->textWidth($tpl['hashtags'][1], $fontSemi, 28, 0.0);
$urlW = $b->textWidth((string) $tpl['url'], $fontSemi, 28, 0.0);
echo "\n  Kicker: " . round($kickerW, 1) . " px | Hashtags: " . round($footW, 1)
    . " px | URL: " . round($urlW, 1) . " px\n";
if ($footW + 32 + $urlW > GogRenderer::CW) {
    echo "  FEHLER: Fusszeile passt nicht in " . GogRenderer::CW . " px\n";
    $fail++;
}

echo "\n" . ($fail === 0 ? "Alles in Ordnung.\n" : "{$fail} Problem(e).\n");
exit($fail === 0 ? 0 : 1);

/** Tintenhöhe eines "H" in Pixel (nur GD). */
function capHeight(string $font, float $px): int
{
    $box = imagettfbbox($px * 0.75, 0, $font, 'H');
    return (int) ($box[1] - $box[7]);
}
