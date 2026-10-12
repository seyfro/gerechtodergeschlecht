<?php
declare(strict_types=1);

/** Ergebnis einer Bildgenerierung: PNG-Daten plus Layout-Bericht für die Tests. */
final class GogRenderResult
{
    public function __construct(
        private ImageBackend $backend,
        public readonly array $layout,
        private int $level
    ) {
    }

    public function png(): string
    {
        return $this->backend->png($this->level);
    }

    public function backend(): ImageBackend
    {
        return $this->backend;
    }
}

/** Farben und Maße einer Vorlagenvariante; Renderer und HTML-Karte nutzen dieselbe Quelle. */
function gog_variant(bool $red): array
{
    return $red
        ? [
            'bg' => '#ec3013',
            'fg' => '#ffffff',
            'kicker' => '#ffffff',
            'muted' => '#ffe0d9',
            'photoBg' => '#ae1800',
            'plate' => '#f3f2f2',
            'logoH' => 80,
            'logoPadY' => 20,
            'logoPadX' => 28,
        ]
        : [
            'bg' => '#f3f2f2',
            'fg' => '#201e1d',
            'kicker' => '#ae1800',
            'muted' => '#605d5d',
            'photoBg' => '#eae9e9',
            'plate' => '#f3f2f2',
            'logoH' => 96,
            'logoPadY' => 0,
            'logoPadX' => 0,
        ];
}

/*
 * Setzt eine Vorlage exakt nach der Canvas-Referenz aus dem Handoff
 * (Methode render() in "Kampagne Wir Väter.dc.html").
 *
 * Feste Werte, die sich aus der Arithmetik ergeben und unabhängig von der Zeilenzahl sind:
 * die zweite Trennlinie liegt immer bei y = 1355, die Fußzeilen-Baseline bei y = 1409.
 */
final class GogRenderer
{
    public const W = 1200;
    public const H = 1500;
    public const PAD = 80;
    public const CW = 1040;

    private ImageBackend $b;
    private array $layout = [];

    public function __construct(private ?string $preferBackend = null)
    {
    }

    /**
     * @param array|null $photo Ergebnis von gog_upload_validate(); null erzeugt die Vorlage
     *                          mit Platzhalterfläche (für preview.php).
     */
    public function render(int $n, ?array $photo = null): GogRenderResult
    {
        $tpl = gog_templates();
        $motifs = $tpl['motifs'];
        if (!isset($motifs[$n])) {
            throw new InvalidArgumentException("Unbekannte Vorlage {$n}");
        }
        $m = $motifs[$n];
        $v = gog_variant((bool) $m['red']);
        $cfg = gog_config();
        $this->layout = ['template' => $n, 'runs' => []];

        $fontHead = gog_font_path(800);
        $fontSemi = gog_font_path(600);
        $fontReg = gog_font_path(400);

        $this->b = gog_backend_create(self::W, self::H, $v['bg'], $this->preferBackend);
        $b = $this->b;

        // 2 – Logokopf. Die weiße Platte der roten Variante liegt unter dem Logo, deshalb
        //     wird die Logobreite vorab aus den Bildmaßen bestimmt.
        $logoPath = (string) $cfg['logo'];
        $logoInfo = @getimagesize($logoPath);
        if ($logoInfo === false) {
            throw new RuntimeException("Logo nicht lesbar: {$logoPath}");
        }
        $logoH = (int) $v['logoH'];
        $plate = (int) $v['logoPadY'];
        $rowH = $logoH + 2 * $plate;
        $logoW = (int) round($logoInfo[0] * $logoH / $logoInfo[1]);
        if ($plate > 0) {
            $b->fillRect(self::PAD, self::PAD, $logoW + 2 * (int) $v['logoPadX'], $rowH, $v['plate']);
        }
        $b->drawLogo($logoPath, self::PAD + (int) $v['logoPadX'], self::PAD + $plate, (float) $logoH, $v['plate']);

        // 3 – Kicker, rechtsbündig auf der Grundlinie des Logokopfs.
        $this->text($tpl['kicker'], self::W - self::PAD, self::PAD + $rowH - 8, $fontSemi, 22, 1.8, $v['kicker'], 'right');

        // 4 – erste Trennlinie
        $y = self::PAD + $rowH + 40;
        $b->fillRect(self::PAD, (int) $y, self::CW, 2, $v['fg']);
        $this->layout['rule1'] = (int) $y;
        $y += 42;

        // 5 – Headline
        $headLines = gog_wrap(
            fn(string $s): float => $b->textWidth($s, $fontHead, 78, -1.5),
            (string) $m['headline'],
            self::CW
        );
        $this->layout['headlineLines'] = count($headLines);
        $first = $y + 62;
        foreach ($headLines as $i => $line) {
            $this->text($line, self::PAD, $first + $i * 82, $fontHead, 78, -1.5, $v['fg']);
        }
        $y = $first + (count($headLines) - 1) * 82 + 14;

        // 6 – Quelle; ihre Grundlinie ist zugleich die Unterkante des oberen Blocks.
        $this->text((string) $m['source'], self::PAD, $y + 30, $fontReg, 24, 0.0, $v['muted']);
        $topEnd = $y + 30;
        $this->layout['topEnd'] = (int) $topEnd;

        // 7 – unteren Block vermessen, bevor das Foto platziert wird
        $stLines = gog_wrap(
            fn(string $s): float => $b->textWidth($s, $fontHead, 46, -0.5),
            (string) $tpl['statement'],
            self::CW
        );
        $bottomH = count($stLines) * 53 + 40 + 2 + 28 + 34;
        $bottomStart = self::H - self::PAD - $bottomH;
        $this->layout['statementLines'] = count($stLines);
        $this->layout['bottomStart'] = (int) $bottomStart;

        // 8 – Foto, senkrecht zwischen oberem und unterem Block zentriert
        $photoH = (int) $m['photoH'];
        $gap = ($bottomStart - $topEnd - $photoH) / 2;
        if ($gap < 0) {
            throw new RuntimeException("Vorlage {$n}: Text zu lang, Foto passt nicht mehr");
        }
        $py = (int) round($topEnd + $gap);
        $this->layout['photo'] = ['x' => self::PAD, 'y' => $py, 'w' => self::CW, 'h' => $photoH];
        if ($photo !== null) {
            $b->drawPhoto($photo, self::PAD, $py, self::CW, $photoH, (float) $cfg['photo_contrast']);
        } else {
            $b->fillRect(self::PAD, $py, self::CW, $photoH, $v['photoBg']);
            $ph = (string) $tpl['placeholder'];
            $w = $b->textWidth($ph, $fontSemi, 28, 0.0);
            $this->text(
                $ph,
                self::PAD + (self::CW - $w) / 2,
                $py + $photoH / 2 + 10,
                $fontSemi,
                28,
                0.0,
                $v['muted']
            );
        }

        // 9 – Statement
        $firstSt = $bottomStart + 40;
        foreach ($stLines as $i => $line) {
            $this->text($line, self::PAD, $firstSt + $i * 53, $fontHead, 46, -0.5, $v['fg']);
        }
        $y = $firstSt + (count($stLines) - 1) * 53 + 12;

        // 10 – zweite Trennlinie (rechnerisch immer 1355)
        $y += 40;
        $b->fillRect(self::PAD, (int) $y, self::CW, 2, $v['fg']);
        $this->layout['rule2'] = (int) $y;

        // 11 – Fußzeile: beide Hashtags links, die Adresse rechtsbündig
        $y += 28 + 26;
        $this->layout['footerBaseline'] = (int) $y;
        $tagW = $this->text((string) $tpl['hashtags'][0], self::PAD, $y, $fontSemi, 28, 0.0, $v['kicker']);
        $this->text((string) $tpl['hashtags'][1], self::PAD + $tagW + 32, $y, $fontSemi, 28, 0.0, $v['kicker']);
        $this->text((string) $tpl['url'], self::W - self::PAD, $y, $fontSemi, 28, 0.0, $v['kicker'], 'right');

        return new GogRenderResult($b, $this->layout, (int) $cfg['png_level']);
    }

    /** Zeichnet einen Textlauf und merkt ihn für den Layout-Bericht. */
    private function text(
        string $s,
        float $x,
        float $baseline,
        string $font,
        float $px,
        float $ls,
        string $hex,
        string $align = 'left'
    ): float {
        $w = $this->b->drawText($s, $x, $baseline, $font, $px, $ls, $hex, $align);
        $this->layout['runs'][] = [
            'text' => $s,
            'x' => $align === 'right' ? $x - $w : $x,
            'baseline' => $baseline,
            'width' => $w,
            'size' => $px,
        ];
        return $w;
    }
}
