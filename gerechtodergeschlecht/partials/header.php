<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

$texts = gog_texts();
?>
<header class="site-head">
  <a href="./"><img src="<?= e(gog_asset('assets/wir-vaeter-logo.png')) ?>" alt="<?= e((string) $texts['logo_alt']) ?>"></a>
  <nav class="site-nav">
<?php foreach ($texts['nav'] as $item): ?>
    <a href="<?= e((string) $item['href']) ?>"><?= e((string) $item['label']) ?></a>
<?php endforeach; ?>
    <a class="ext" href="<?= e((string) $texts['nav_ext']['href']) ?>"><?= e((string) $texts['nav_ext']['label']) ?></a>
  </nav>
</header>
