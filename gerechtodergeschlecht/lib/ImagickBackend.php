<?php
declare(strict_types=1);

/*
 * Bild-Backend auf Basis von Imagick. Lokal (WAMP) nicht verfügbar, deshalb wird dieser Pfad
 * erst am Host geprüft; config 'backend' => 'gd' schaltet ihn notfalls ab.
 *
 * Imagick rechnet mit 72 dpi, dort ist ein Punkt gleich ein Pixel. Letterspacing kommt über
 * ImagickDraw::setTextKerning, die Breite über queryFontMetrics()['textWidth'] – gemessen wird
 * damit wie in GD die Vorschubbreite inklusive Letterspacing.
 */
final class ImagickBackend implements ImageBackend
{
    private Imagick $im;
    private int $w;
    private int $h;

    public static function available(): bool
    {
        return class_exists('Imagick') && class_exists('ImagickDraw') && class_exists('ImagickPixel');
    }

    public function __construct(int $w, int $h, string $bgHex)
    {
        $this->w = $w;
        $this->h = $h;
        $this->im = new Imagick();
        $this->im->newImage($w, $h, new ImagickPixel($bgHex), 'png');
        $this->im->setImageColorspace(Imagick::COLORSPACE_SRGB);
    }

    private function draw(string $font, float $px, float $ls, string $hex = '#000000'): ImagickDraw
    {
        $d = new ImagickDraw();
        $d->setFont($font);
        $d->setFontSize($px);
        $d->setTextKerning($ls);
        $d->setFillColor(new ImagickPixel($hex));
        $d->setTextAntialias(true);
        return $d;
    }

    /** Geschützte Leerzeichen zu normalen; Imagick interpretiert keine Entities. */
    private function prep(string $s): string
    {
        return str_replace("\u{00A0}", ' ', $s);
    }

    public function textWidth(string $text, string $font, float $px, float $ls): float
    {
        if ($text === '') {
            return 0.0;
        }
        $m = $this->im->queryFontMetrics($this->draw($font, $px, $ls), $this->prep($text));
        return (float) ($m['textWidth'] ?? 0.0);
    }

    public function fillRect(int $x, int $y, int $w, int $h, string $hex): void
    {
        if ($w <= 0 || $h <= 0) {
            return;
        }
        $d = new ImagickDraw();
        $d->setFillColor(new ImagickPixel($hex));
        $d->rectangle($x, $y, $x + $w - 1, $y + $h - 1);
        $this->im->drawImage($d);
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
        $d = $this->draw($font, $px, $ls, $hex);
        // annotateImage setzt die Baseline, wie Canvas mit textBaseline = 'alphabetic'.
        $this->im->annotateImage($d, $x0, $baseline, 0.0, $this->prep($text));
        return $width;
    }

    public function drawLogo(string $pngPath, float $x, float $y, float $h, string $underHex): float
    {
        $logo = new Imagick();
        $logo->readImage($pngPath);
        $w = (int) round($logo->getImageWidth() * $h / $logo->getImageHeight());
        $logo->setImageBackgroundColor(new ImagickPixel($underHex));
        $logo->resizeImage($w, (int) round($h), Imagick::FILTER_LANCZOS, 1.0);
        $this->im->compositeImage($logo, Imagick::COMPOSITE_OVER, (int) round($x), (int) round($y));
        $logo->clear();
        return (float) $w;
    }

    public function drawPhoto(array $photo, int $x, int $y, int $w, int $h, float $contrast): void
    {
        $src = new Imagick();
        try {
            $src->readImage((string) $photo['path']);
        } catch (Throwable $e) {
            throw new GogUploadException(400, 'unreadable', $e->getMessage());
        }
        $src->setImageOrientation((int) $photo['orientation']);
        if (method_exists($src, 'autoOrient')) {
            $src->autoOrient();
        } else {
            self::orient($src, (int) $photo['orientation']);
        }
        // cropThumbnailImage entspricht dem Cover-Crop: passend skalieren, mittig beschneiden.
        $src->cropThumbnailImage($w, $h);
        $src->transformImageColorspace(Imagick::COLORSPACE_GRAY);
        self::contrast($src, $contrast);
        $this->im->compositeImage($src, Imagick::COMPOSITE_OVER, $x, $y);
        $src->clear();
    }

    /** Lineare Kontrastdehnung um den Mittelwert, entspricht CSS contrast(f). */
    private static function contrast(Imagick $im, float $factor): void
    {
        if ($factor <= 0.0 || abs($factor - 1.0) < 0.001) {
            return;
        }
        $q = $im->getQuantumRange()['quantumRangeLong'];
        $edge = 0.5 - 0.5 / $factor;
        $im->levelImage($edge * $q, 1.0, (1.0 - $edge) * $q);
    }

    /** Fallback, wenn autoOrient fehlt (sehr alte Imagick-Versionen). */
    private static function orient(Imagick $im, int $orientation): void
    {
        $transparent = new ImagickPixel('transparent');
        if (in_array($orientation, [2, 5, 7], true)) {
            $im->flopImage();
        }
        if ($orientation === 4) {
            $im->flipImage();
        }
        if ($orientation === 3) {
            $im->rotateImage($transparent, 180);
        } elseif ($orientation === 6 || $orientation === 7) {
            $im->rotateImage($transparent, 90);
        } elseif ($orientation === 5 || $orientation === 8) {
            $im->rotateImage($transparent, -90);
        }
        $im->setImageOrientation(Imagick::ORIENTATION_TOPLEFT);
    }

    public function png(int $level): string
    {
        $this->im->setImageFormat('png');
        $this->im->setOption('png:compression-level', (string) $level);
        return $this->im->getImagesBlob();
    }

    /** Nur für Tests: Farbe eines Pixels als [r, g, b]. */
    public function pixel(int $x, int $y): array
    {
        $c = $this->im->getImagePixelColor($x, $y)->getColor();
        return [(int) $c['r'], (int) $c['g'], (int) $c['b']];
    }
}
