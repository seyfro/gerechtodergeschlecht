<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

/**
 * Seitenkopf. Erwartet optional:
 *   $pageTitle string  Titel (sonst der aus texts.php)
 *   $noindex   bool    true bei Fehlerseiten
 */
$texts = gog_texts();
$title = isset($pageTitle) ? (string) $pageTitle : (string) $texts['meta']['title'];
if (!headers_sent()) {
    header('Content-Type: text/html; charset=utf-8');
}
?>
<!doctype html>
<html lang="de">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title><?= e($title) ?></title>
<meta name="description" content="<?= e((string) $texts['meta']['description']) ?>">
<?php if (!empty($noindex)): ?>
<meta name="robots" content="noindex">
<?php endif; ?>
<meta property="og:type" content="website">
<meta property="og:title" content="<?= e((string) $texts['meta']['title']) ?>">
<meta property="og:description" content="<?= e((string) $texts['meta']['description']) ?>">
<meta property="og:image" content="preview.php?template=1">
<link rel="stylesheet" href="<?= e(gog_asset('assets/styles.css')) ?>">
<link rel="stylesheet" href="<?= e(gog_asset('assets/page.css')) ?>">
<script src="<?= e(gog_asset('assets/app.js')) ?>" defer></script>
</head>
<body>
<div class="wrap">
