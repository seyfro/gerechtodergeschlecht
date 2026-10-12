<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

/*
 * Alle Texte der Seite an einer Stelle. Die Bildtexte (Headlines, Statement, Hashtags)
 * liegen in templates.php, weil sie Eingaben des Renderers sind.
 * Geschützte Leerzeichen (\u{00A0}) halten Zahl und Prozentzeichen zusammen.
 */
return [
    'meta' => [
        'title' => 'Gerecht oder Geschlecht? Fragt auch die Väter. – Wir Väter',
        'description' => 'Eine Antwort auf gerechtodergeschlecht.at: sechs Vorlagen mit Fakten zu Trennungsvätern, '
            . 'Doppelresidenz und Care-Arbeit. Foto hochladen, Bild herunterladen, teilen.',
    ],

    'logo_alt' => 'Wir Väter – Initiative für verantwortungsvolle Vaterschaft',

    'nav' => [
        ['href' => '#warum', 'label' => 'Warum'],
        ['href' => '#fakten', 'label' => 'Fakten'],
        ['href' => '#mitmachen', 'label' => 'Mitmachen'],
        ['href' => '#vorlagen', 'label' => 'Vorlagen'],
    ],
    'nav_ext' => ['href' => 'https://www.wir-vaeter.at', 'label' => 'wir-vaeter.at'],

    'hero' => [
        'kicker' => 'Eine Antwort auf gerechtodergeschlecht.at',
        'title' => 'Gerecht oder Geschlecht? Fragt auch die Väter.',
        'text' => 'Die Kampagne des BMFWF zeigt, wo das Geschlecht über Chancen entscheidet. Ein Bereich fehlt: '
            . 'was nach einer Trennung aus Vätern wird – und was das für Mütter und Kinder bedeutet.',
        'image_hint' => 'Symbolbild: Vater und Kind, Alltag – z. B. Frühstückstisch',
        'image_alt' => 'Vater und Kleinkind beim Frühstück am Küchentisch',
    ],

    'facts' => [
        [
            'num' => "60\u{00A0}%",
            'accent' => false,
            'text' => 'der Kinder sehen ihren Vater nach der Trennung höchstens einmal alle zwei Wochen.',
            'source' => 'Statistik Austria, Unterhaltsbefragung 2021',
        ],
        [
            'num' => "33\u{00A0}%",
            'accent' => false,
            'text' => 'sehen ihren Vater seltener oder nie.',
            'source' => 'Statistik Austria, Unterhaltsbefragung 2021',
        ],
        [
            'num' => "<\u{00A0}5\u{00A0}%",
            'accent' => true,
            'text' => "Doppelresidenz in Österreich. Schweden: 42,5\u{00A0}%, Finnland: 23,8\u{00A0}%, Belgien: 20\u{00A0}%.",
            'source' => 'EU-SILC 2021',
        ],
    ],

    'why' => [
        'title' => 'Das Residenzmodell verliert dreifach.',
        'text' => 'Nach einer Trennung betreut in Österreich meist die Mutter, der Vater hat Kontaktrecht und zahlt '
            . 'Unterhalt. Das ist kein Modell für Gleichstellung – es ist „Frauen an den Herd“ mit anderen Mitteln.',
        'image_hint' => 'Symbolbild: leerer Kinderstuhl oder gepackter Wochenendrucksack',
        'image_alt' => 'Gepackter Kinderrucksack und ein einzelner Schuh vor der Wohnungstür',
        'items' => [
            [
                'title' => 'Die Mutter',
                'text' => 'Teilzeitfalle, finanzielle Abhängigkeit, Pensionslücke. Alleinige Betreuung über zwölf Tage '
                    . 'am Stück ist überfordernd. Kein Pensionssplitting holt zurück, was jahrelange '
                    . 'Einkommensreduktion kostet.',
            ],
            [
                'title' => 'Der Vater',
                'text' => 'Will betreuen, wird auf die Ernährerrolle reduziert. Wer mehr Zeit mit dem Kind möchte und '
                    . 'daran gehindert wird, zahlt für etwas, das er nicht will.',
            ],
            [
                'title' => 'Das Kind',
                'text' => 'Lernt früh: Mütter versorgen, Väter zahlen. Im Wechselmodell schneiden Kinder bei fast allen '
                    . 'Indikatoren besser ab (Nielsen 2018, FAMOD 2021).',
            ],
        ],
    ],

    'familygap' => [
        'title' => 'Family Gap, nicht Gender Gap.',
        'image_hint' => 'Symbolbild: Vater mit Kinderwagen am Arbeitsweg',
        'image_alt' => 'Vater schiebt einen Kinderwagen über eine Straßenkreuzung in Wien',
        'paragraphs' => [
            'Care-Arbeit führt zu Einkommensnachteilen, nicht das biologische Geschlecht. Frauen ohne Kinder oder mit '
                . "kurzer Elternzeit verdienen laut DIW nur rund 2\u{00A0}% weniger als Männer. Betroffen sind alle, "
                . 'die Familienarbeit übernehmen – auch Väter.',
            'Mehr Erwerbsarbeit für Frauen geht nur Hand in Hand mit mehr Familienzeit für Männer. Väterrechte und '
                . 'Feminismus sind zwei Seiten einer Medaille.',
            'Der Europarat empfiehlt seit 2015 (Resolution 2079) die Doppelresidenz als gesetzlichen Regelfall. '
                . 'Belgien, Schweden, Dänemark, Portugal und andere haben das umgesetzt. Österreich nicht.',
        ],
    ],

    'join' => [
        'title' => 'Mitmachen',
        'steps' => [
            [
                'num' => '01',
                'title' => 'Vorlage wählen',
                'text' => 'Sechs Motive mit Fakten zu Trennungsvätern, Doppelresidenz und Care-Arbeit.',
            ],
            [
                'num' => '02',
                'title' => 'Foto hochladen, Bild herunterladen',
                'text' => 'Dein Gesicht, dein Statement. Foto auswählen, „Bild herunterladen“ klicken – das fertige Bild '
                    . "(1200\u{00A0}×\u{00A0}1500\u{00A0}px, Schwarz-Weiß) landet als PNG in deinen Downloads. "
                    . 'Wir speichern dein Foto nicht.',
            ],
            [
                'num' => '03',
                'title' => 'Teilen',
                'text' => 'Auf Social Media mit beiden Hashtags – so landet die Antwort dort, wo die Frage gestellt wird.',
            ],
        ],
        'button' => 'Zu den Vorlagen',
    ],

    'templates' => [
        'title' => 'Die Vorlagen',
        'intro' => 'Foto wählen, dann „Bild herunterladen“. Dein Foto wird in Schwarz-Weiß in die Vorlage gesetzt und '
            . "als PNG (1200\u{00A0}×\u{00A0}1500\u{00A0}px) heruntergeladen. JPG, PNG oder WebP, max. 12\u{00A0}MB. "
            . 'Das Foto wird nur für die Erstellung verwendet und danach sofort gelöscht.',
        'card_label' => 'Vorlage %d',
        'choose' => 'Foto wählen',
        'download' => 'Bild herunterladen',
        'share' => 'Teilen / in Fotos sichern',
    ],

    // Statusmeldungen neben dem Button (nur mit JavaScript sichtbar).
    'status' => [
        'chosen' => 'Foto gewählt',
        'working' => 'Wird erstellt …',
        'done' => 'Heruntergeladen',
        'error' => 'Fehler beim Erstellen',
    ],

    // Servermeldungen nach Fehlercode; generate.php liefert den Code, JSON und Fehlerseite den Text.
    'errors' => [
        'no_file' => 'Bitte wähle zuerst ein Foto aus.',
        'too_large' => "Das Foto ist zu groß (max. 12\u{00A0}MB).",
        'unsupported_type' => 'Bitte ein Foto im Format JPG, PNG oder WebP wählen.',
        'too_many_pixels' => "Das Foto hat zu viele Pixel (max. 6000\u{00A0}×\u{00A0}6000).",
        'unreadable' => 'Das Foto konnte nicht gelesen werden.',
        'partial' => 'Der Upload wurde unterbrochen. Bitte noch einmal versuchen.',
        'token' => 'Die Seite war zu lange geöffnet – bitte neu laden und noch einmal versuchen.',
        'rate_limited' => 'Zu viele Anfragen – bitte eine Minute warten.',
        'bad_template' => 'Unbekannte Vorlage.',
        'method' => 'Diese Adresse nimmt nur das Formular der Vorlagen entgegen.',
        'server' => 'Beim Erstellen ist ein Fehler passiert. Bitte versuch es später noch einmal.',
    ],

    'error_page' => [
        'title' => 'Das hat leider nicht geklappt.',
        'back' => 'Zurück zur Vorlage',
    ],

    'poster' => [
        'title' => 'Ein modernes Familienrecht stärkt Müttern den Rücken für den Beruf und gibt Vätern die '
            . 'Verantwortung für ihre Kinder.',
        'line' => 'Wir Väter e.V. · Initiative für verantwortungsvolle Vaterschaft · ',
    ],

    'legal' => [
        ['href' => 'https://www.wir-vaeter.at/impressum/', 'label' => 'Impressum'],
        ['href' => 'https://www.wir-vaeter.at/datenschutz/', 'label' => 'Datenschutz'],
    ],
];
