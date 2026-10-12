<?php
declare(strict_types=1);

/*
 * Der One-Pager. Reine Ausgabe; die Bildgenerierung steckt in generate.php.
 * Alle Texte kommen aus texts.php, die Motive aus templates.php.
 */

define('GOG', 1);
require __DIR__ . '/lib/bootstrap.php';

$texts = gog_texts();
$tpl = gog_templates();
$csrf = gog_form_token();

header('Cache-Control: no-cache');

/**
 * Bildfläche: echtes Symbolbild, wenn es unter assets/img/ liegt. Fehlt es, steht dort eine
 * gekennzeichnete Platzhalterfläche mit dem gewünschten Motiv – so sieht man auf einen Blick,
 * welches Bild noch fehlt, statt eine scheinbar leere Fläche zu sehen.
 *
 * $section ist der Abschnitt aus texts.php; von dort kommen die Bildbeschreibung für
 * Screenreader (image_alt) und der Motivwunsch für die Platzhalterfläche (image_hint).
 * $lazy ist für das Hero-Bild aus: Es steht ganz oben und soll sofort laden.
 */
function gog_figure(string $file, array $section, string $class, bool $lazy = true): void
{
    $hint = (string) ($section['image_hint'] ?? '');
    $path = GOG_ROOT . '/assets/img/' . $file;
    if (is_file($path)) {
        $alt = (string) ($section['image_alt'] ?? '');
        echo '<div class="figure ' . e($class) . ' grayscale">'
            . '<img src="' . e(gog_asset('assets/img/' . $file)) . '" alt="' . e($alt) . '"'
            . ($lazy ? ' loading="lazy"' : '') . ' decoding="async">'
            . '</div>';
        return;
    }
    echo '<div class="figure figure-empty ' . e($class) . '">'
        . '<span>' . e($hint) . '</span>'
        . '<!-- Bild ablegen unter assets/img/' . e($file) . ' -->'
        . '</div>';
}

include __DIR__ . '/partials/head.php';
include __DIR__ . '/partials/header.php';
?>

<section class="hero">
  <div>
    <div class="kicker"><?= e((string) $texts['hero']['kicker']) ?></div>
    <h1><?= e((string) $texts['hero']['title']) ?></h1>
  </div>
  <p><?= e((string) $texts['hero']['text']) ?></p>
</section>

<?php gog_figure('hero.jpg', $texts['hero'], 'figure-hero', false); ?>

<section id="fakten" class="facts">
<?php foreach ($texts['facts'] as $f): ?>
  <div>
    <div class="num<?= !empty($f['accent']) ? ' accent' : '' ?>"><?= e((string) $f['num']) ?></div>
    <p><?= e((string) $f['text']) ?></p>
    <div class="src"><?= e((string) $f['source']) ?></div>
  </div>
<?php endforeach; ?>
</section>

<section id="warum" class="split">
  <div>
    <h2><?= e((string) $texts['why']['title']) ?></h2>
    <p><?= e((string) $texts['why']['text']) ?></p>
    <?php gog_figure('warum.jpg', $texts['why'], 'figure-43'); ?>
  </div>
  <div class="stack">
<?php foreach ($texts['why']['items'] as $item): ?>
    <div>
      <h3><?= e((string) $item['title']) ?></h3>
      <p><?= e((string) $item['text']) ?></p>
    </div>
<?php endforeach; ?>
  </div>
</section>

<section class="split split-top">
  <?php gog_figure('familygap.jpg', $texts['familygap'], 'figure-43'); ?>
  <div>
    <h2><?= e((string) $texts['familygap']['title']) ?></h2>
<?php foreach ($texts['familygap']['paragraphs'] as $p): ?>
    <p><?= e((string) $p) ?></p>
<?php endforeach; ?>
  </div>
</section>

<section id="mitmachen" class="section">
  <h2><?= e((string) $texts['join']['title']) ?></h2>
  <div class="steps">
<?php foreach ($texts['join']['steps'] as $i => $step): ?>
    <div>
      <div class="num"><?= e((string) $step['num']) ?></div>
      <h3><?= e((string) $step['title']) ?></h3>
      <p><?= e((string) $step['text']) ?></p>
<?php if ($i === 0): ?>
      <a class="btn btn-primary" href="#vorlagen"><?= e((string) $texts['join']['button']) ?></a>
<?php elseif ($i === 2): ?>
      <div class="tags">
        <span class="tag tag-accent"><?= e((string) $tpl['hashtags'][0]) ?></span>
        <span class="tag tag-outline"><?= e((string) $tpl['hashtags'][1]) ?></span>
        <span class="tag tag-neutral"><?= e((string) $tpl['url']) ?></span>
      </div>
<?php endif; ?>
    </div>
<?php endforeach; ?>
  </div>
</section>

<section id="vorlagen" class="section">
  <div class="tpl-head-row">
    <h2><?= e((string) $texts['templates']['title']) ?></h2>
    <p><?= e((string) $texts['templates']['intro']) ?></p>
  </div>
  <div class="tpl-grid">
<?php foreach ($tpl['motifs'] as $n => $m) {
    include __DIR__ . '/partials/card.php';
} ?>
  </div>
</section>

<section class="poster">
  <h2><?= e((string) $texts['poster']['title']) ?></h2>
  <div class="line">
    <?= e((string) $texts['poster']['line']) ?><a href="https://<?= e((string) $tpl['url']) ?>"><?= e((string) $tpl['url']) ?></a>
  </div>
</section>

<?php include __DIR__ . '/partials/footer.php'; ?>
