<?php
declare(strict_types=1);

/*
 * Erzeugt Testfotos in tests/out/fixtures/.
 *
 *   php tests/fixtures.php
 *
 * Jedes Bild trägt dieselben Marker, damit Zuschnitt und Drehung im Ergebnis prüfbar sind:
 *   - ein kräftiger Helligkeitsverlauf von dunkel OBEN nach hell UNTEN. Er überlebt jeden
 *     mittigen Zuschnitt und verrät eine fehlende Drehung sofort: Ein um 90 Grad falsch
 *     liegendes Bild hat den Verlauf waagrecht, oben und unten sind dann gleich hell.
 *   - eine weiße Scheibe genau in der Mitte,
 *   - ein schwarzer Balken an der oberen Kante (nur bei passendem Seitenverhältnis sichtbar).
 */

$dir = __DIR__ . '/out/fixtures';
if (!is_dir($dir)) {
    mkdir($dir, 0775, true);
}

/** Motiv mit Markern (siehe Kopfkommentar). */
function gog_fixture_scene(int $w, int $h): GdImage
{
    $im = imagecreatetruecolor($w, $h);
    for ($y = 0; $y < $h; $y++) {
        $t = $y / max(1, $h - 1);
        // dunkelblau oben nach hellgelb unten: starker Kontrast in der Helligkeit
        $c = imagecolorallocate($im, (int) (10 + 230 * $t), (int) (25 + 210 * $t), (int) (70 + 150 * $t));
        imageline($im, 0, $y, $w - 1, $y, $c);
    }
    // schwarzer Balken oben (10 % der Höhe)
    imagefilledrectangle($im, 0, 0, $w - 1, (int) ($h * 0.1), imagecolorallocate($im, 0, 0, 0));
    // weiße Scheibe in der Mitte
    $r = (int) (min($w, $h) * 0.22);
    imagefilledellipse($im, intdiv($w, 2), intdiv($h, 2), $r, $r, imagecolorallocate($im, 255, 255, 255));
    return $im;
}

$made = [];

// --- JPEG in drei Seitenverhältnissen ----------------------------------------------------
foreach ([['portrait', 1200, 1800], ['landscape', 2400, 1200], ['square', 1500, 1500]] as [$name, $w, $h]) {
    $im = gog_fixture_scene($w, $h);
    imagejpeg($im, "{$dir}/{$name}.jpg", 88);
    $made[] = "{$name}.jpg ({$w}x{$h})";
}

// --- PNG mit Transparenz (Scheibe deckend, Rest durchsichtig) ---------------------------
$png = imagecreatetruecolor(1000, 1000);
imagealphablending($png, false);
imagesavealpha($png, true);
imagefilledrectangle($png, 0, 0, 999, 999, imagecolorallocatealpha($png, 0, 0, 0, 127));
imagealphablending($png, true);
imagefilledellipse($png, 500, 500, 700, 700, imagecolorallocate($png, 250, 240, 30));
imagefilledrectangle($png, 0, 0, 999, 99, imagecolorallocate($png, 0, 0, 0));
imagealphablending($png, false);
imagepng($png, "{$dir}/alpha.png", 6);
$made[] = 'alpha.png (1000x1000, transparent)';

// --- WebP --------------------------------------------------------------------------------
if (function_exists('imagewebp')) {
    $webp = gog_fixture_scene(1600, 900);
    imagewebp($webp, "{$dir}/photo.webp", 85);
    $made[] = 'photo.webp (1600x900)';
}

// --- JPEG mit EXIF-Orientierung 6 --------------------------------------------------------
// Aufrechtes Querformat um 90 Grad gegen den Uhrzeigersinn ablegen; Orientierung 6 sagt der
// Anzeige, dass sie im Uhrzeigersinn zurückdrehen muss.
$upright = gog_fixture_scene(1600, 1200);
$stored = imagerotate($upright, 90, 0);
ob_start();
imagejpeg($stored, null, 88);
$jpg = (string) ob_get_clean();

// Minimales APP1-Segment: Exif-Header, TIFF-Kopf (big endian), eine IFD mit Tag 0x0112 = 6.
$app1 = "\xFF\xE1" . pack('n', 34) . "Exif\0\0"
    . "MM\0\x2A" . pack('N', 8)
    . pack('n', 1)
    . pack('nnN', 0x0112, 3, 1) . pack('nn', 6, 0)
    . pack('N', 0);
$exifJpeg = substr($jpg, 0, 2) . $app1 . substr($jpg, 2);
file_put_contents("{$dir}/exif6.jpg", $exifJpeg);
$check = @exif_read_data("{$dir}/exif6.jpg");
$orient = is_array($check) ? (int) ($check['Orientation'] ?? 0) : 0;
if ($orient !== 6) {
    fwrite(STDERR, "FEHLER: exif6.jpg meldet Orientierung {$orient} statt 6\n");
    exit(1);
}
if (@getimagesize("{$dir}/exif6.jpg") === false) {
    fwrite(STDERR, "FEHLER: exif6.jpg ist nach dem Einfügen des EXIF-Segments nicht mehr lesbar\n");
    exit(1);
}
$made[] = 'exif6.jpg (1200x1600 gespeichert, Orientierung 6)';

// --- Ungültige Dateien für die Fehlerpfade ----------------------------------------------
file_put_contents("{$dir}/text.txt", "Das ist kein Bild.\n");
$made[] = 'text.txt (kein Bild)';
file_put_contents("{$dir}/truncated.jpg", substr((string) file_get_contents("{$dir}/portrait.jpg"), 0, 4000));
$made[] = 'truncated.jpg (abgeschnitten)';

echo "Testfotos in {$dir}:\n";
foreach ($made as $m) {
    echo "  - {$m}\n";
}
