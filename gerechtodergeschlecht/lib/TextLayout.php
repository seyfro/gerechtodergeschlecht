<?php
declare(strict_types=1);

/*
 * Wortweiser Zeilenumbruch, identisch zur Funktion wrap() der Canvas-Referenz:
 * Wörter werden an normalen Leerzeichen (U+0020) getrennt, ein Wort wird an die Zeile
 * angehängt, solange die gemessene Breite ≤ $maxW bleibt; ein zu breites einzelnes Wort
 * bleibt trotzdem allein in seiner Zeile. Geschützte Leerzeichen (U+00A0) trennen nicht.
 * "\n" erzwingt einen Umbruch.
 *
 * @param callable(string): float $measure liefert die Breite eines Strings in Pixel
 * @return string[] Zeilen
 */
function gog_wrap(callable $measure, string $text, float $maxW): array
{
    $lines = [];
    foreach (explode("\n", $text) as $paragraph) {
        $line = '';
        foreach (explode(' ', $paragraph) as $word) {
            if ($word === '') {
                continue;
            }
            $test = $line === '' ? $word : $line . ' ' . $word;
            if ($line !== '' && $measure($test) > $maxW) {
                $lines[] = $line;
                $line = $word;
            } else {
                $line = $test;
            }
        }
        if ($line !== '') {
            $lines[] = $line;
        }
    }
    return $lines;
}
