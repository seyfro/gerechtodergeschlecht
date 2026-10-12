<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

/**
 * Eine Vorlagenkarte: maßstäblich verkleinerte Vorschau plus Formular.
 * Erwartet: $n (int), $m (Motiv aus templates.php), $csrf (string).
 * Die Farben stammen aus derselben Funktion wie im Renderer, damit Karte und PNG nie
 * auseinanderlaufen.
 */
$texts = gog_texts();
$tpl = gog_templates();
$v = gog_variant((bool) $m['red']);
$style = sprintf(
    '--bg:%s;--fg:%s;--kicker:%s;--muted:%s;--photo-bg:%s;--plate:%s;'
        . '--logo-h:%d;--logo-pad-x:%d;--logo-pad-y:%d;--photo-h:%d',
    $v['bg'],
    $v['fg'],
    $v['kicker'],
    $v['muted'],
    $v['photoBg'],
    $v['plate'],
    $v['logoH'],
    $v['logoPadX'],
    $v['logoPadY'],
    (int) $m['photoH']
);
$label = sprintf((string) $texts['templates']['card_label'], $n);
?>
<div class="tpl-card" id="vorlage-<?= (int) $n ?>">
  <div class="tpl-frame">
    <div class="tpl" style="<?= e($style) ?>" role="img" aria-label="<?= e($label . ': ' . str_replace("\u{00A0}", ' ', (string) $m['headline'])) ?>">
      <div class="tpl-top">
        <div class="tpl-logo"><img src="<?= e(gog_asset('assets/wir-vaeter-logo.png')) ?>" alt=""></div>
        <div class="tpl-kicker"><?= e((string) $tpl['kicker']) ?></div>
      </div>
      <div class="tpl-rule"></div>
      <h3 class="tpl-headline"><?= e((string) $m['headline']) ?></h3>
      <div class="tpl-source"><?= e((string) $m['source']) ?></div>
      <div class="tpl-fill"></div>
      <div class="tpl-photo">
        <img alt="" hidden>
        <div class="tpl-photo-hint"><?= e((string) $tpl['placeholder']) ?></div>
      </div>
      <div class="tpl-fill"></div>
      <p class="tpl-statement"><?= e((string) $tpl['statement']) ?></p>
      <div class="tpl-rule tpl-rule-foot"></div>
      <div class="tpl-foot">
        <span><?= e((string) $tpl['hashtags'][0]) ?></span>
        <span><?= e((string) $tpl['hashtags'][1]) ?></span>
        <span class="url"><?= e((string) $tpl['url']) ?></span>
      </div>
    </div>
  </div>

  <form class="tpl-form" method="post" action="generate.php" enctype="multipart/form-data"
        data-status-chosen="<?= e((string) $texts['status']['chosen']) ?>"
        data-status-working="<?= e((string) $texts['status']['working']) ?>"
        data-status-done="<?= e((string) $texts['status']['done']) ?>"
        data-status-error="<?= e((string) $texts['status']['error']) ?>"
        data-error-too-large="<?= e((string) $texts['errors']['too_large']) ?>"
        data-error-type="<?= e((string) $texts['errors']['unsupported_type']) ?>"
        data-error-rate="<?= e((string) $texts['errors']['rate_limited']) ?>"
        data-max-bytes="<?= (int) gog_config('max_upload_bytes') ?>">
    <input type="hidden" name="template" value="<?= (int) $n ?>">
    <input type="hidden" name="csrf" value="<?= e($csrf) ?>">
    <label class="btn btn-secondary">
      <?= e((string) $texts['templates']['choose']) ?>
      <input type="file" name="photo" accept="image/jpeg,image/png,image/webp" required hidden
             aria-label="<?= e($label . ': ' . $texts['templates']['choose']) ?>">
    </label>
    <button class="btn btn-primary" type="submit"><?= e((string) $texts['templates']['download']) ?></button>
    <button class="btn btn-secondary tpl-share" type="button" hidden><?= e((string) $texts['templates']['share']) ?></button>
    <span class="tpl-status" aria-live="polite"></span>
  </form>
</div>
