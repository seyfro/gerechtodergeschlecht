# Gerecht oder Geschlecht? Fragt auch die Väter.

Kampagnen-One-Pager für [wir-vaeter.at](https://www.wir-vaeter.at) als Antwort auf die
BMFWF-Kampagne gerechtodergeschlecht.at. Kern ist die **Vorlagengenerierung**: Ein Motiv
wählen, ein Foto hochladen, fertiges Social-Media-Bild herunterladen
(1200 × 1500 px PNG, Foto in Schwarz-Weiß).

Reines PHP, kein Composer, kein Build-Schritt. Die Bildgenerierung läuft serverseitig;
JavaScript ist nur Komfort, ohne JavaScript funktioniert alles genauso.

Design-Referenz: [`design_handoff_kampagne_onepager/`](design_handoff_kampagne_onepager/)
(bleibt unverändert im Repo, Abweichungen siehe unten).

## Aufbau

```
gerechtodergeschlecht/     ← genau dieser Ordner wird auf den Server geladen
  index.php                One-Pager mit den sechs Vorlagen
  generate.php             POST: Vorlage + Foto → PNG als Download
  preview.php              GET: Vorlage ohne Foto als PNG (og:image, Sichtprüfung)
  config.php               Grenzwerte, Backend-Wahl, Pfade
  templates.php            die sechs Motive und die festen Bildtexte
  texts.php                alle Texte der Seite, inklusive Fehlermeldungen
  lib/                     Renderer, Bild-Backends, Upload-Prüfung, Sicherheit, HTTP
  partials/                Seitenrahmen und Vorlagenkarte
  assets/                  Stylesheets, app.js, Schriften, Logo, Symbolbilder
  var/                     Laufzeitdaten (Secret, Rate-Limit, Vorschau-Cache)
tests/                     Testfotos, Render-Matrix, HTTP- und Browsertest
tools/                     Diagnose: Schriftmaße, Umbruchvergleich, Umgebungsprüfung
```

## Texte ändern

Alles, was auf der Seite steht, liegt in
[`texts.php`](gerechtodergeschlecht/texts.php) – auch die Statusmeldungen neben dem Button
und die Fehlermeldungen. Die Texte **im Bild** (Headlines, Quelle, Statement, Hashtags)
stehen in [`templates.php`](gerechtodergeschlecht/templates.php), weil sie Eingaben des
Renderers sind. Kein HTML in beiden Dateien, nur Text.

Zwischen Zahl und Prozentzeichen steht ein geschütztes Leerzeichen (`\u{00A0}`), damit der
Umbruch „60 %“ nicht trennt. Ein `\n` in einer Headline erzwingt an dieser Stelle einen
Zeilenumbruch.

## Entwickeln

```bash
php -S 127.0.0.1:8080 -t gerechtodergeschlecht \
    -d upload_max_filesize=16M -d post_max_size=16M -d memory_limit=512M
```

Die `-d`-Angaben sind nötig, weil eine Standard-php.ini oft nur 2 MB Upload erlaubt.

## Tests

```bash
php tools/font-check.php        # Schriftmaße und Zeilenumbrüche aller Bildtexte
php tests/fixtures.php          # Testfotos erzeugen (hoch, quer, quadrat, Alpha, WebP, EXIF)
php tests/render-all.php        # 6 Vorlagen x 7 Fälle rendern und prüfen (~930 Prüfungen)
php tests/http-smoke.php        # Endpunkte, Fehlercodes, Rate-Limit (~31 Prüfungen)
node tests/browser.mjs          # Vorschau und Download im echten Browser (~17 Prüfungen)
```

`render-all.php` und `browser.mjs` brauchen vorher `fixtures.php`. Der Browsertest braucht
Node ab Version 22 und Chrome oder Edge (anderer Pfad: `CHROME=... node tests/browser.mjs`).
Die gerenderten Bilder landen in `tests/out/` und lohnen einen Blick, weil kein Test
beurteilen kann, ob ein Bild *schön* ist.

Umbrüche gegenprüfen: `php -S 127.0.0.1:8090 -t .` starten und
`http://127.0.0.1:8090/tools/wrap-browser.php` öffnen. Dort läuft derselbe Umbruch mit
denselben Schriftdateien im Browser. Beide Wege liefern derzeit identische Zeilen.

## Wie das Bild entsteht

Maßgeblich ist die Canvas-Referenz im Handoff (Methode `render()` in
`Kampagne Wir Väter.dc.html`); [`lib/Renderer.php`](gerechtodergeschlecht/lib/Renderer.php)
bildet dieselbe Reihenfolge nach. Aus der Arithmetik ergeben sich zwei feste Werte, die als
Test abgesichert sind: die untere Trennlinie liegt immer bei y = 1355, die Fußzeile bei
y = 1409.

Zwei Eigenheiten von GD prägen [`lib/GdBackend.php`](gerechtodergeschlecht/lib/GdBackend.php):

- **Schriftgröße ist Punkt bei 96 dpi**, also `pt = px × 0.75`.
- **`imagettfbbox` misst Tinte, nicht Vorschub.** Ein angehängtes Leerzeichen zählt aber mit
  seinem vollen Vorschub, deshalb liefert `bbox(s . " ") − bbox(" ")` die gesuchte Breite –
  ohne eigenen Schriftdatei-Parser.

Letterspacing kennt GD nicht; bei Bedarf wird zeichenweise gezeichnet, wobei jede Position
aus der Vorschubbreite ihres Präfixes stammt. So bleibt das Kerning erhalten.

Das Foto wird **zuerst zugeschnitten und dann gedreht**: Der mittige Zuschnitt ist unter
allen acht EXIF-Orientierungen derselbe, und so muss nie das große Bild rotiert werden.

## Sicherheit und Datenschutz

- Hochgeladene Fotos werden **nicht gespeichert**. Es bleibt bei der temporären Datei von
  PHP, die direkt nach der Antwort gelöscht wird.
- Kein Google Fonts, keine externen Aufrufe, kein Cookie, kein CAPTCHA. Die Schriften liegen
  selbst gehostet unter `assets/fonts/` (SIL Open Font License).
- Das Formular-Token ist ein HMAC über einen Zeitstempel, also ohne Session und ohne Cookie.
- Rate-Limit 10 Bilder pro IP und Minute. IP-Adressen werden nur als HMAC abgelegt, IPv6 auf
  das /64-Präfix gekürzt. Ist der Laufzeitordner nicht beschreibbar, wird protokolliert und
  **nicht** blockiert.
- Uploads: höchstens 12 MB, höchstens 6000 × 6000 px, Typ nur per `finfo` bestimmt (nie aus
  Dateiendung oder Browserangabe), unvollständige Dateien werden an der fehlenden Endmarke
  erkannt und abgelehnt.

## Server

Voraussetzungen (`php tools/check-env.php` prüft alles auf einmal):

| | |
|---|---|
| PHP | ab 8.1 |
| Bild | `gd` mit FreeType, JPEG, PNG, WebP **oder** `imagick` |
| weitere | `exif`, `fileinfo`, `mbstring` |
| Limits | `upload_max_filesize` ≥ 16M, `post_max_size` ≥ 16M, `memory_limit` ≥ 256M |
| nginx | `client_max_body_size` ≥ 16m |
| Rechte | `var/` beschreibbar |

Zielort ist `https://www.wir-vaeter.at/gerechtodergeschlecht/`. Die Seite läuft dort neben
WordPress auf nginx, also ohne `.htaccess`; Konfigurationsdateien und Partials schützen sich
selbst über eine Konstante und geben bei direktem Aufruf nichts aus.

**Deploy** (manuell per FTP, wie in den anderen Projekten):

1. Ordner `gerechtodergeschlecht/` hochladen. `tests/` und `tools/` bleiben lokal.
2. Inhalte von `var/` auf dem Server **nie** überschreiben.
3. `tools/check-env.php` kurz mit hochladen, im Browser aufrufen, danach wieder löschen.
4. `preview.php?template=3` aufrufen und ansehen. Sieht das Bild falsch aus, in
   `config.php` (oder einer nicht versionierten `config.local.php`)
   `'backend' => 'gd'` setzen.
5. Die Seite aus einem etwaigen Seiten-Cache ausnehmen.

## Symbolbilder

Die drei Bilder liegen in `assets/img/` und heißen `hero.jpg` (21:9),
`warum.jpg` und `familygap.jpg` (beide 4:3). Ein Bild austauschen heißt: Datei ersetzen,
gleicher Name, gleiches Seitenverhältnis – der Rest passiert von selbst, inklusive
Cache-Buster. Fehlt eine Datei, zeigt die Seite an ihrer Stelle eine gekennzeichnete
Platzhalterfläche mit dem gewünschten Motiv statt einer scheinbar leeren Fläche.

Die Bildbeschreibungen für Screenreader stehen als `image_alt` in `texts.php`, direkt neben
dem Motivwunsch `image_hint`. Beides gehört zusammen gepflegt, wenn ein Bild wechselt.

Die aktuellen Dateien sind 1024 px breit. Für den Hero-Bereich ist das auf großen oder
hochauflösenden Bildschirmen knapp; falls die Originale größer vorliegen, lohnt der
Austausch gegen etwa 2000 px Breite.

## Abweichungen vom Handoff

| Handoff | hier | warum |
|---|---|---|
| Pfad `/kampagne/` | `/gerechtodergeschlecht/` | auf wir-vaeter.at gibt es bereits zwei andere Kampagnen-Beiträge |
| Imagick bevorzugt, GD als Rückfall | beide hinter einem Interface, Imagick bevorzugt | GD ist der einzige lokal prüfbare Weg; die Imagick-Variante ist erst am Server zu testen |
| Kontrast `imagefilter(CONTRAST, -8)` | `-4` | GD rechnet `((100 − arg)/100)²`. Gemessen ergibt −8 den Faktor 1,17, −4 dagegen 1,08 wie im CSS |
| Session-CSRF | HMAC-Token ohne Session | kein Cookie nötig, unempfindlich gegen Seiten-Caching |
| Vorschau-Thumbnails über `preview.php` | HTML-Karten wie im Prototyp | eine Anfrage weniger pro Karte, und die Karte zeigt das gewählte Foto sofort |
| Anleitungstexte des Prototyps | ergänzt | das Foto geht jetzt zum Server; Formate, Größe und der Hinweis auf das sofortige Löschen stehen deshalb dabei |
