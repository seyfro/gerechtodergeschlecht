<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

/*
 * Alle Eingaben der Bildgenerierung (Render-Spezifikation im Handoff-README).
 *
 * Zwischen Zahl und Prozentzeichen steht ein geschütztes Leerzeichen (\u{00A0}): Der wortweise
 * Umbruch trennt nur an normalen Leerzeichen, sonst könnte „60“ am Zeilenende stehen und „%“
 * am Anfang der nächsten Zeile. Ein "\n" in einer Headline erzwingt einen Umbruch, falls eine
 * Zeile einmal bewusst anders gesetzt werden soll.
 */
return [
    'kicker' => 'GERECHT ODER GESCHLECHT?',
    'statement' => '„Deshalb setze ich mich für Doppelresidenz und geteilte Care-Arbeit ein!“',
    'hashtags' => ['#gerechtodergeschlecht', '#wirvaeter'],
    'url' => 'www.wir-vaeter.at',
    'placeholder' => 'Dein Foto hier einfügen',

    'motifs' => [
        1 => [
            'red' => false,
            'photoH' => 520,
            'headline' => "60\u{00A0}% der Kinder sehen ihren Vater nach der Trennung höchstens alle zwei Wochen.",
            'source' => 'Statistik Austria, Unterhaltsbefragung 2021',
        ],
        2 => [
            'red' => false,
            'photoH' => 520,
            'headline' => "Weitere 33\u{00A0}% sehen ihren Vater seltener. Oder nie.",
            'source' => 'Statistik Austria, Unterhaltsbefragung 2021',
        ],
        3 => [
            'red' => true,
            'photoH' => 520,
            'headline' => "Doppelresidenz: Schweden 42,5\u{00A0}%. Österreich unter 5\u{00A0}%.",
            'source' => 'EU-SILC 2021',
        ],
        4 => [
            'red' => false,
            'photoH' => 480,
            'headline' => 'Der Europarat empfiehlt die Doppelresidenz als Regelfall. Seit 2015. Österreich wartet.',
            'source' => 'Resolution 2079, Parlamentarische Versammlung des Europarats',
        ],
        5 => [
            'red' => false,
            'photoH' => 480,
            'headline' => "Ohne Kinder liegt der Gehaltsunterschied bei 2\u{00A0}%. Es ist ein Family Gap, kein Gender Gap.",
            'source' => 'DIW-Studie, Frauen 25–35 ohne Kinder oder mit kurzer Elternzeit',
        ],
        6 => [
            'red' => true,
            'photoH' => 480,
            'headline' => 'Residenzmodell heißt: Die Mutter betreut, der Vater zahlt. Frauen an den Herd.',
            'source' => 'Das gängige Modell nach Trennungen in Österreich',
        ],
    ],
];
