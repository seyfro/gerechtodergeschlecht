<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

$texts = gog_texts();
?>
<footer class="site-foot">
<?php foreach ($texts['legal'] as $item): ?>
  <a href="<?= e((string) $item['href']) ?>"><?= e((string) $item['label']) ?></a>
<?php endforeach; ?>
</footer>
</div>
</body>
</html>
