<?php
declare(strict_types=1);

/*
 * Bild-Backend auf Basis von GD mit FreeType.
 *
 * Zwei Eigenheiten von GD, auf denen alles hier aufbaut:
 *
 * 1. Schriftgröße. imagettftext/imagettfbbox erwarten Punkt und rastern mit fest
 *    eingebauten 96 dpi, also gilt pt = px * 0.75. (Empirisch geprüft: "H" bei 75 pt
 *    ist genau so hoch wie die Versalhöhe der Schrift bei 100 px.)
 *
 * 2. Textbreite. imagettfbbox liefert die Tinten-Box, nicht die Vorschubbreite. GD zählt
 *    aber ein abschließendes Leerzeichen mit seinem vollen Vorschub mit, deshalb ist
 *        adv(s) = bbox(s . " ")[2] - bbox(" ")[2]
 *    die gesuchte Vorschubbreite inklusive des Kernings, das GD anwendet. Damit braucht es
 *    keinen eigenen TTF-Parser.
 *
 * Letterspacing kennt GD nicht. Bei ls != 0 wird deshalb zeichenweise gezeichnet, wobei die
 * Position jedes Zeichens aus der Vorschubbreite seines Präfixes stammt:
 *     x_i = x0 + adv(s[0..i]) - adv(s[i]) + i * ls
 * Das erhält das Kerning zwischen den Zeichen und verhindert, dass sich Rundungsfehler
 * über die Zeile aufsummieren.
 */
final class GdBackend implements ImageBackend
{
    /** Punkt je Pixel: libgd rastert mit GD_RESOLUTION = 96 dpi. */
    private const PT_PER_PX = 0.75;

    private GdImage $im;
    private int $w;
    private int $h;
    /** @var array<string,int> */
    private array $colors = [];
    /** @var array<string,float> */
    private array $advCache = [];

    public static function available(): bool
    {
        return extension_loaded('gd')
            && function_exists('imagettftext')
            && !empty(gd_info()['FreeType Support']);
    }

    public function __construct(int $w, int $h, string $bgHex)
    {
        $im = imagecreatetruecolor($w, $h);
        if ($im === false) {
            throw new RuntimeException('GD: Bild konnte nicht angelegt werden');
        }
        $this->im = $im;
        $this->w = $w;
        $this->h = $h;
        imagealphablending($this->im, true);
        imagesavealpha($this->im, false);
        imagefilledrectangle($this->im, 0, 0, $w - 1, $h - 1, $this->color($bgHex));
    }

    private function color(string $hex): int
    {
        if (!isset($this->colors[$hex])) {
            [$r, $g, $b] = gog_hex_rgb($hex);
            $c = imagecolorallocate($this->im, $r, $g, $b);
            if ($c === false) {
                throw new RuntimeException("GD: Farbe {$hex} konnte nicht belegt werden");
            }
            $this->colors[$hex] = $c;
        }
        return $this->colors[$hex];
    }

    /**
     * Bereitet einen String für GD auf: geschützte Leerzeichen werden zu normalen (gleicher
     * Vorschub, aber kein Risiko eines fehlenden Glyphs), und "&" wird als Entity maskiert,
     * weil imagettftext Sequenzen wie &#65; sonst als Zeichen interpretiert.
     */
    private function prep(string $s): string
    {
        return str_replace(["\u{00A0}", '&'], [' ', '&#38;'], $s);
    }

    /** Vorschubbreite in Pixel, ohne Letterspacing. */
    private function advance(string $s, string $font, float $px): float
    {
        if ($s === '') {
            return 0.0;
        }
        $key = $font . '|' . $px . '|' . $s;
        if (isset($this->advCache[$key])) {
            return $this->advCache[$key];
        }
        $pt = $px * self::PT_PER_PX;
        $spaceKey = $font . '|' . $px . '|@space';
        if (!isset($this->advCache[$spaceKey])) {
            $sb = imagettfbbox($pt, 0, $font, ' ');
            if ($sb === false) {
                throw new RuntimeException('GD: Text konnte nicht vermessen werden');
            }
            $this->advCache[$spaceKey] = (float) $sb[2];
        }
        $box = imagettfbbox($pt, 0, $font, $this->prep($s) . ' ');
        if ($box === false) {
            throw new RuntimeException('GD: Text konnte nicht vermessen werden');
        }
        $this->advCache[$key] = (float) $box[2] - $this->advCache[$spaceKey];
        return $this->advCache[$key];
    }

    public function textWidth(string $text, string $font, float $px, float $ls): float
    {
        if ($text === '') {
            return 0.0;
        }
        return $this->advance($text, $font, $px) + $ls * mb_strlen($text);
    }

    public function fillRect(int $x, int $y, int $w, int $h, string $hex): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        imagefilledrectangle($this->im, $x, $y, $x + $w - 1, $y + $h - 1, $this->color($hex));
    }

    public function drawText(
        string $text,
        float $x,
        float $baseline,
        string $font,
        float $px,
        float $ls,
        string $hex,
        string $align = 'left'
    ): float {
        if ($text === '') {
            return 0.0;
        }
        $width = $this->textWidth($text, $font, $px, $ls);
        $x0 = $align === 'right' ? $x - $width : $x;
        $pt = $px * self::PT_PER_PX;
        $color = $this->color($hex);
        $y = (int) round($baseline);

        if ($ls === 0.0) {
            imagettftext($this->im, $pt, 0, (int) round($x0), $y, $color, $font, $this->prep($text));
            return $width;
        }

        // Zeichenweise: Position aus der Vorschubbreite des Präfixes, damit Kerning erhalten bleibt.
        $chars = mb_str_split(str_replace("\u{00A0}", ' ', $text));
        $prefix = '';
        foreach ($chars as $i => $ch) {
            $prefix .= $ch;
            if ($ch === ' ') {
                continue;
            }
            $gx = $x0 + $this->advance($prefix, $font, $px) - $this->advance($ch, $font, $px) + $i * $ls;
            imagettftext($this->im, $pt, 0, (int) round($gx), $y, $color, $font, $this->prep($ch));
        }
        return $width;
    }

    public function drawLogo(string $pngPath, float $x, float $y, float $h, string $underHex): float
    {
        $logo = @imagecreatefrompng($pngPath);
        if ($logo === false) {
            throw new RuntimeException("GD: Logo konnte nicht geladen werden ({$pngPath})");
        }
        $lw = imagesx($logo);
        $lh = imagesy($logo);
        $w = (int) round($lw * $h / $lh);

        // GD multipliziert Alpha beim Skalieren nicht vor. Ein transparentes PNG direkt
        // verkleinert bekommt dunkle Ränder, deshalb wird es zuerst in voller Auflösung
        // auf den Untergrund gerechnet, auf dem es später ohnehin sitzt.
        $flat = imagecreatetruecolor($lw, $lh);
        if ($flat === false) {
            throw new RuntimeException('GD: Logo-Zwischenbild fehlgeschlagen');
        }
        [$r, $g, $b] = gog_hex_rgb($underHex);
        imagealphablending($flat, false);
        imagefilledrectangle($flat, 0, 0, $lw - 1, $lh - 1, (int) imagecolorallocate($flat, $r, $g, $b));
        imagealphablending($flat, true);
        imagecopy($flat, $logo, 0, 0, 0, 0, $lw, $lh);

        imagealphablending($this->im, false);
        imagecopyresampled($this->im, $flat, (int) round($x), (int) round($y), 0, 0, $w, (int) round($h), $lw, $lh);
        imagealphablending($this->im, true);
        return (float) $w;
    }

    public function drawPhoto(array $photo, int $x, int $y, int $w, int $h, float $contrast): void
    {
        $src = self::load((string) $photo['path'], (string) $photo['mime']);
        $crop = self::coverCrop($src, (int) $photo['w'], (int) $photo['h'], (int) $photo['orientation'], $w, $h);
        unset($src);

        imagefilter($crop, IMG_FILTER_GRAYSCALE);
        $arg = self::contrastArg($contrast);
        if ($arg !== 0) {
            imagefilter($crop, IMG_FILTER_CONTRAST, $arg);
        }
        // Blending an: transparente Bereiche eines PNG zeigen den Vorlagenhintergrund,
        // genau wie in der Canvas-Referenz.
        imagealphablending($this->im, true);
        imagecopy($this->im, $crop, $x, $y, 0, 0, $w, $h);
    }

    /**
     * GDs Kontrastfilter rechnet mit ((100 - arg) / 100)^2. Für den CSS-Faktor 1.08 ergibt
     * das -4; der im Handoff genannte Wert -8 wäre 1.17 und damit deutlich zu hart.
     */
    public static function contrastArg(float $factor): int
    {
        if ($factor <= 0.0) {
            return 0;
        }
        return (int) round(100.0 - 100.0 * sqrt($factor));
    }

    private static function load(string $path, string $mime): GdImage
    {
        if ($mime === 'image/jpeg') {
            $im = @imagecreatefromjpeg($path);
        } elseif ($mime === 'image/png') {
            $im = @imagecreatefrompng($path);
        } elseif ($mime === 'image/webp') {
            $im = @imagecreatefromwebp($path);
        } else {
            $im = false;
        }
        if ($im === false) {
            throw new GogUploadException(400, 'unreadable', "GD kann {$mime} nicht lesen");
        }
        if (!imageistruecolor($im)) {
            imagepalettetotruecolor($im);
        }
        return $im;
    }

    /**
     * Cover-Crop wie im Canvas (auf max(zielB/breite, zielH/hoehe) skalieren, mittig
     * beschneiden) - aber als Quellrechteck, damit nie das ganze Bild skaliert wird.
     * Die EXIF-Drehung wird erst auf dem kleinen Ausschnitt angewandt; der mittige
     * Ausschnitt ist unter allen acht EXIF-Transformationen derselbe.
     */
    private static function coverCrop(GdImage $src, int $w, int $h, int $orientation, int $tw, int $th): GdImage
    {
        $swap = in_array($orientation, [5, 6, 7, 8], true);
        $vw = $swap ? $h : $w;
        $vh = $swap ? $w : $h;
        $s = max($tw / $vw, $th / $vh);
        $cropVw = $tw / $s;
        $cropVh = $th / $s;
        $cwS = min((float) $w, $swap ? $cropVh : $cropVw);
        $chS = min((float) $h, $swap ? $cropVw : $cropVh);
        $sx = (int) round(($w - $cwS) / 2);
        $sy = (int) round(($h - $chS) / 2);

        $dstW = $swap ? $th : $tw;
        $dstH = $swap ? $tw : $th;
        $dst = imagecreatetruecolor($dstW, $dstH);
        if ($dst === false) {
            throw new GogUploadException(500, 'server', 'GD: Ausschnitt fehlgeschlagen');
        }
        imagealphablending($dst, false);
        imagesavealpha($dst, true);
        imagecopyresampled($dst, $src, 0, 0, $sx, $sy, $dstW, $dstH, (int) round($cwS), (int) round($chS));
        return self::orient($dst, $orientation);
    }

    /**
     * EXIF-Orientierung 1..8 auf GD abbilden. imagerotate dreht gegen den Uhrzeigersinn,
     * ein mit 90 Grad CCW abgelegtes Bild (Orientierung 6) braucht also -90.
     */
    private static function orient(GdImage $im, int $orientation): GdImage
    {
        if ($orientation <= 1 || $orientation > 8) {
            return $im;
        }
        if ($orientation === 2 || $orientation === 5 || $orientation === 7) {
            imageflip($im, IMG_FLIP_HORIZONTAL);
        }
        if ($orientation === 4) {
            imageflip($im, IMG_FLIP_VERTICAL);
        }
        $deg = 0;
        if ($orientation === 3) {
            $deg = 180;
        } elseif ($orientation === 5 || $orientation === 8) {
            $deg = 90;
        } elseif ($orientation === 6 || $orientation === 7) {
            $deg = -90;
        }
        if ($deg !== 0) {
            $transparent = (int) imagecolorallocatealpha($im, 0, 0, 0, 127);
            $out = imagerotate($im, $deg, $transparent);
            if ($out === false) {
                throw new RuntimeException('GD: Drehung fehlgeschlagen');
            }
            imagealphablending($out, false);
            imagesavealpha($out, true);
            $im = $out;
        }
        return $im;
    }

    public function png(int $level): string
    {
        ob_start();
        imagepng($this->im, null, $level);
        return (string) ob_get_clean();
    }

    /** Nur für Tests: Farbe eines Pixels als [r, g, b]. */
    public function pixel(int $x, int $y): array
    {
        $c = imagecolorat($this->im, $x, $y);
        return [($c >> 16) & 0xFF, ($c >> 8) & 0xFF, $c & 0xFF];
    }
}
