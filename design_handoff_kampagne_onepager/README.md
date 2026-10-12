# Handoff: Kampagnen-One-Pager „Gerecht oder Geschlecht? Fragt auch die Väter.“

## Overview
One-Pager für www.wir-vaeter.at als Antwort auf die BMFWF-Kampagne gerechtodergeschlecht.at. Kern ist die **dynamische Vorlagengenerierung**: Besucher:innen wählen eines von sechs Motiven, laden ein Foto hoch und erhalten ein fertiges Social-Media-Bild (1200 × 1500 px PNG, Foto in Schwarz-Weiß) zum Download.

**Stack-Vorgabe: PHP only.** Die Bildgenerierung läuft serverseitig in PHP (GD oder Imagick), kein Node, kein Client-Canvas als Pflichtpfad. Der Client braucht nur ein HTML-Formular; JavaScript ist optionale Verbesserung (Live-Vorschau), nicht Voraussetzung.

## About the Design Files
Die beigelegten `.dc.html`-Dateien sind **Design-Referenzen in HTML** – Prototypen, die Aussehen und Verhalten zeigen. Sie sind nicht zum direkten Übernehmen gedacht. Aufgabe ist, diese Designs in PHP-Templates nachzubauen und die im Prototyp clientseitig (Canvas) gelöste Bildgenerierung serverseitig in PHP zu implementieren. Die Canvas-Logik in `Kampagne Wir Väter.dc.html` (Klasse `Component`, Methode `render`) ist die **exakte Layout-Spezifikation** für den PHP-Renderer – Koordinaten, Schriftgrößen und Reihenfolge dort 1:1 übernehmen.

## Fidelity
**High-fidelity.** Farben, Typografie, Abstände und Layout sind final. Design-System: „Modernist“ – flach, Archivo, Rot auf Hellgrau, 2px-Linien, Radius 0, linksbündig, Fotos in Schwarz-Weiß.

---

## Teil 1 – Vorlagengenerierung (Fokus)

### Endpunkte
| Methode | Pfad | Zweck |
|---|---|---|
| GET | `/kampagne/` | One-Pager (Teil 2) inkl. Vorlagenraster |
| POST | `/kampagne/generate.php` | Nimmt `template` (1–6) + `photo` (Datei), liefert PNG als Download |
| GET | `/kampagne/preview.php?template=N` | (optional) Vorlage ohne Foto als PNG für Vorschau-Thumbnails |

### Formular pro Vorlage
```html
<form method="post" action="generate.php" enctype="multipart/form-data">
  <input type="hidden" name="template" value="1">
  <input type="hidden" name="csrf" value="…">
  <label class="btn btn-secondary">Foto wählen
    <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required hidden>
  </label>
  <button class="btn btn-primary" type="submit">Bild herunterladen</button>
</form>
```
Ohne JS: Submit → Response ist `Content-Type: image/png`, `Content-Disposition: attachment; filename="wir-vaeter-gerecht-oder-geschlecht-N.png"`.
Mit JS (optional): Foto lokal per `URL.createObjectURL` in die Vorschau setzen (`object-fit:cover; filter:grayscale(1) contrast(1.08)`), Formular per `fetch` senden, Blob als Download auslösen. Status-Text neben dem Button: „Wird erstellt …“ → „Gespeichert“ / „Fehler beim Erstellen“.

### Validierung (server)
- MIME via `finfo`, erlaubt: jpeg, png, webp. Max 12 MB. Max 6000 × 6000 px.
- EXIF-Orientation korrigieren (Imagick `autoOrient()` / GD `exif_read_data` + `imagerotate`).
- `template` ∈ {1..6}, sonst 400.
- Rate-Limit (z. B. 10 Generierungen / IP / Minute). Uploads nicht dauerhaft speichern; Temp-Datei nach Response löschen.

### Templatedaten (`templates.php`)
```php
return [
  1 => ['red'=>false,'photoH'=>520,'headline'=>'60 % der Kinder sehen ihren Vater nach der Trennung höchstens alle zwei Wochen.','source'=>'Statistik Austria, Unterhaltsbefragung 2021'],
  2 => ['red'=>false,'photoH'=>520,'headline'=>'Weitere 33 % sehen ihren Vater seltener. Oder nie.','source'=>'Statistik Austria, Unterhaltsbefragung 2021'],
  3 => ['red'=>true, 'photoH'=>520,'headline'=>'Doppelresidenz: Schweden 42,5 %. Österreich unter 5 %.','source'=>'EU-SILC 2021'],
  4 => ['red'=>false,'photoH'=>480,'headline'=>'Der Europarat empfiehlt die Doppelresidenz als Regelfall. Seit 2015. Österreich wartet.','source'=>'Resolution 2079, Parlamentarische Versammlung des Europarats'],
  5 => ['red'=>false,'photoH'=>480,'headline'=>'Ohne Kinder liegt der Gehaltsunterschied bei 2 %. Es ist ein Family Gap, kein Gender Gap.','source'=>'DIW-Studie, Frauen 25–35 ohne Kinder oder mit kurzer Elternzeit'],
  6 => ['red'=>true, 'photoH'=>480,'headline'=>'Residenzmodell heißt: Die Mutter betreut, der Vater zahlt. Frauen an den Herd.','source'=>'Das gängige Modell nach Trennungen in Österreich'],
];
```
Konstanten: `STATEMENT = '„Deshalb setze ich mich für Doppelresidenz und geteilte Care-Arbeit ein!“'`, Kicker `GERECHT ODER GESCHLECHT?`, Footer `#gerechtodergeschlecht`, `#wirvaeter`, `www.wir-vaeter.at`.

### Render-Spezifikation (1200 × 1500 px)
Canvas `W=1200, H=1500, PAD=80, CW=1040`. Alle Textblöcke linksbündig bei x=80, außer Kicker und URL (rechtsbündig an x=1120). Schrift: **Archivo** (TTF für 400, 600, 800 im Repo ablegen, `assets/fonts/Archivo-*.ttf`, Lizenz OFL).

Farben nach Variante:
| | hell (`red=false`) | rot (`red=true`) |
|---|---|---|
| Hintergrund | `#f3f2f2` | `#ec3013` |
| Text/Linien `fg` | `#201e1d` | `#ffffff` |
| Kicker & Hashtags | `#ae1800` | `#ffffff` |
| Quelle `muted` | `#605d5d` | `#ffe0d9` |
| Foto-Platzhalter | `#eae9e9` | `#ae1800` |

Ablauf (y von oben):
1. **Hintergrund** füllen.
2. **Logo-Zeile** ab y=80. Hell: Logo `assets/wir-vaeter-logo.png` Höhe 96, x=80. Rot: weiße Platte `#f3f2f2` 20px oben/unten, 28px links/rechts um das Logo (Höhe 80) → Zeilenhöhe `rowH = 120` (hell: 96).
3. **Kicker** „GERECHT ODER GESCHLECHT?“ Archivo 600 22px, Letterspacing 1,8px, Farbe Kicker, rechtsbündig x=1120, Baseline `80 + rowH − 8`.
4. **Linie** 2px `fg`, y = `80 + rowH + 40`, Breite 1040.
5. **Headline** Archivo 800 78px, Letterspacing −1,5px, Zeilenhöhe 82, Umbruch bei 1040px (wortweise). Erste Baseline `y_linie + 42 + 62`.
6. **Quelle** Archivo 400 24px, Farbe muted, Baseline 30px unter letzter Headline-Zeile (+14 Ausgleich). Ende = `topEnd`.
7. **Unterer Block** messen: Statement Archivo 800 46px, Letterspacing −0,5px, Zeilenhöhe 53, Umbruch bei 1040. `bottomH = zeilen*53 + 40 + 2 + 28 + 34`. `bottomStart = 1500 − 80 − bottomH`.
8. **Foto** 1040 × `photoH`, vertikal zentriert zwischen `topEnd` und `bottomStart`. Cover-Crop (skalieren auf max(1040/w, photoH/h), mittig beschneiden). **Graustufen + Kontrast 1,08**: Imagick `setImageColorspace(GRAY)` + `sigmoidalContrastImage`/`levelImage`; GD `imagefilter(IMG_FILTER_GRAYSCALE)` + `imagefilter(IMG_FILTER_CONTRAST, -8)`.
9. **Statement** ab `bottomStart`, erste Baseline +40, Farbe `fg`.
10. **Linie** 2px `fg`, 40px unter letzter Statement-Zeile (+12 Ausgleich).
11. **Footer** Baseline `linie + 28 + 26`: Archivo 600 28px, Farbe Kicker. `#gerechtodergeschlecht` bei x=80, `#wirvaeter` 32px rechts davon, `www.wir-vaeter.at` rechtsbündig x=1120.

Referenz: Methode `render()` in `Kampagne Wir Väter.dc.html` – dort steht dieselbe Sequenz als Canvas-Code.

### Empfohlene PHP-Struktur
```
kampagne/
  index.php          One-Pager
  generate.php       POST-Handler → PNG
  lib/Renderer.php   class Renderer { public function render(int $template, string $photoPath): \Imagick|GdImage }
  lib/TextLayout.php Wortumbruch + Textmessung (Imagick queryFontMetrics / GD imagettfbbox)
  templates.php
  assets/fonts/, assets/wir-vaeter-logo.png
```
Imagick bevorzugen (bessere Textmessung, Letterspacing via `setTextKerning`). GD als Fallback, dann Letterspacing per Zeichen-für-Zeichen-Zeichnen.

### Tests
- Alle 6 Vorlagen mit Hoch-, Quer- und Quadratfoto rendern; Ausgabe 1200 × 1500, kein Text über den Rand.
- Rote Varianten: Logo-Platte sichtbar, Text weiß.
- EXIF-gedrehtes Handyfoto korrekt orientiert.
- Ungültige Datei → 400 mit verständlicher Meldung auf der Seite.

---

## Teil 2 – One-Pager (Kontext, niedrigere Priorität)
Max-Breite 1200px, Padding 24px, Sektionen durch 2px-Linien `#201e1d` getrennt, alles linksbündig.

1. **Header** – Logo (Höhe 44) links, Nav rechts (14px/600): Warum, Fakten, Mitmachen, Vorlagen, wir-vaeter.at (rot `#ae1800`).
2. **Hero** – Kicker „Eine Antwort auf gerechtodergeschlecht.at“ (13px/600, uppercase, `#ae1800`), H1 „Gerecht oder Geschlecht? Fragt auch die Väter.“ (clamp 40–76px, 800, lh 1, ls −.025em), Absatz 19px. Darunter Bild 21:9 (Graustufen).
3. **Zahlen** – 3 Spalten, 2px-Trennlinien: `60 %` / `33 %` / `< 5 %` (rot), 64px/800; Text 15px; Quelle 12px `#605d5d`.
4. **Warum** – 2 Spalten: H2 „Das Residenzmodell verliert dreifach.“ (36px) + Absatz + Bild 4:3; rechts drei Einträge mit 2px-Linien: Die Mutter / Der Vater / Das Kind (H3 18px, Text 15px `#444141`).
5. **Family Gap** – Bild 4:3 links, H2 „Family Gap, nicht Gender Gap.“ + 3 Absätze 16px rechts.
6. **Mitmachen** – 3 Spalten mit 2px-Oberlinie, Nummern 01/02/03 (13px rot), H3 20px, Text 15px; Button „Zu den Vorlagen“ (`.btn-primary`, Label linksbündig); Tags `#gerechtodergeschlecht` (accent), `#wirvaeter` (outline), `www.wir-vaeter.at` (neutral).
7. **Die Vorlagen** – Raster `repeat(auto-fill, minmax(280px,1fr))`, Gap 32/24px. Jede Karte: skalierte Vorschau (Aspect 1200/1500) + Formular aus Teil 1.
8. **Poster** – Vollflächig `#ec3013`, weißer Text: „Ein modernes Familienrecht stärkt Müttern den Rücken für den Beruf und gibt Vätern die Verantwortung für ihre Kinder.“ (clamp 32–60px/800) + Zeile „Wir Väter e.V. · Initiative für verantwortungsvolle Vaterschaft · www.wir-vaeter.at“.

Exakte Texte: siehe `Kampagne Wir Väter.dc.html`.

## Design Tokens
- Ground `#f3f2f2`, Surface `#eae9e9`, Text `#201e1d`, Accent `#ec3013`, Accent-700 `#ae1800`, Accent-200 `#ffe0d9`, Neutral-700 `#605d5d`, Neutral-800 `#444141`.
- Font: Archivo 400/600/800 (Google Fonts oder self-hosted).
- Radius 0 überall. Linien 2px. Spacing 4/8/12/16/24/32px.
- Buttons: `.btn-primary` roter Vollton, Label linksbündig; Hover eine Stufe dunkler (`#dd2b0f`); Focus `outline:2px solid #ec3013; offset 2px`.
- Fotos immer `filter:grayscale(1) contrast(1.08)` (Web) bzw. serverseitig graustufig (PNG).

## Assets
- `assets/wir-vaeter-logo.png` (3099 × 1047, transparent) – Vereinslogo.
- Symbolbilder (Hero, Warum, Family Gap) liegen noch nicht vor; Bildflächen sind Platzhalter.
- `_ds/…/styles.css` – Design-System-Stylesheet (Tokens + `.btn`, `.tag`, `.grayscale`).

## Files
- `Kampagne Wir Väter.dc.html` – One-Pager inkl. Canvas-Referenzimplementierung der Vorlagengenerierung (Methode `render`).
- `Vorlagen Wir Väter.dc.html` – die sechs Motive in Originalgröße 1200 × 1500 als statische Referenz.
- `styles.css` – Design-System-Tokens.
- `assets/wir-vaeter-logo.png`
