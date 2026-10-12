<?php
declare(strict_types=1);

/*
 * Referenz für die Zeilenumbrüche: führt denselben Umbruchalgorithmus im Browser aus, mit
 * denselben Schriftdateien, die auch der PHP-Renderer benutzt.
 *
 * Aufruf (vom Projektstammverzeichnis aus):
 *   php -S 127.0.0.1:8090 -t .
 *   dann http://127.0.0.1:8090/tools/wrap-browser.php öffnen
 *
 * Die Ausgabe daneben legen und mit "php tools/font-check.php" vergleichen. Unterschiede
 * entstehen, wenn eine Zeile knapp an der Umbruchbreite liegt: Der Browser kennt das
 * Kerning aus der GPOS-Tabelle, GD und Imagick nicht. Ein "\n" in templates.php legt eine
 * strittige Zeile dann fest.
 */

define('GOG', 1);
require __DIR__ . '/../gerechtodergeschlecht/lib/bootstrap.php';

$tpl = gog_templates();
$data = [
    'cw' => GogRenderer::CW,
    'statement' => $tpl['statement'],
    'motifs' => array_map(
        static fn(int $n, array $m): array => ['n' => $n, 'headline' => $m['headline']],
        array_keys($tpl['motifs']),
        array_values($tpl['motifs'])
    ),
];
header('Content-Type: text/html; charset=utf-8');
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<title>Zeilenumbrüche im Browser</title>
<style>
  @font-face { font-family: "ArchivoLocal"; font-weight: 800; src: url("../gerechtodergeschlecht/assets/fonts/Archivo-ExtraBold.ttf") format("truetype"); }
  body { font: 14px/1.5 system-ui, sans-serif; margin: 24px; max-width: 900px; }
  h1 { font-size: 20px; }
  table { border-collapse: collapse; width: 100%; margin-bottom: 24px; }
  th, td { text-align: left; padding: 4px 8px; border-bottom: 1px solid #ddd; vertical-align: top; }
  td.w { text-align: right; font-variant-numeric: tabular-nums; white-space: nowrap; }
  .tight { color: #b45309; }
  code { background: #f2f2f2; padding: 0 4px; }
</style>
</head>
<body>
<h1>Zeilenumbrüche im Browser</h1>
<p>Gemessen mit <code>ArchivoLocal</code> aus <code>assets/fonts/</code> und derselben
Umbruchlogik wie im Renderer. Umbruchbreite <?= (int) $data['cw'] ?> px.
Zum Vergleich: <code>php tools/font-check.php</code>.</p>
<div id="out">Schrift wird geladen …</div>

<script>
const DATA = <?= json_encode($data, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES) ?>;

function wrap(ctx, text, maxW) {
  const lines = [];
  for (const para of text.split('\n')) {
    let line = '';
    for (const w of para.split(' ')) {
      if (!w) continue;
      const test = line ? line + ' ' + w : w;
      if (line && ctx.measureText(test).width > maxW) { lines.push(line); line = w; }
      else { line = test; }
    }
    if (line) lines.push(line);
  }
  return lines;
}

function table(title, ctx, text, maxW) {
  const lines = wrap(ctx, text, maxW);
  let html = '<h2>' + title + ' – ' + lines.length + ' Zeilen</h2><table>';
  for (const line of lines) {
    const w = ctx.measureText(line).width;
    const cls = w > maxW - 30 ? ' class="tight"' : '';
    html += '<tr><td class="w"' + cls + '>' + w.toFixed(1) + ' px</td><td>'
      + line.replace(/ /g, '_').replace(/[&<>]/g, c => ({'&':'&amp;','<':'&lt;','>':'&gt;'}[c])) + '</td></tr>';
  }
  return html + '</table>';
}

(async () => {
  await document.fonts.load('800 78px ArchivoLocal');
  await document.fonts.load('800 46px ArchivoLocal');
  await document.fonts.ready;
  const ctx = document.createElement('canvas').getContext('2d');
  let html = '';
  for (const m of DATA.motifs) {
    ctx.font = '800 78px ArchivoLocal';
    ctx.letterSpacing = '-1.5px';
    html += table('Vorlage ' + m.n + ' – Headline (78 px, ls -1,5)', ctx, m.headline, DATA.cw);
  }
  ctx.font = '800 46px ArchivoLocal';
  ctx.letterSpacing = '-0.5px';
  html += table('Statement (46 px, ls -0,5)', ctx, DATA.statement, DATA.cw);
  document.getElementById('out').innerHTML = html;
})();
</script>
</body>
</html>
