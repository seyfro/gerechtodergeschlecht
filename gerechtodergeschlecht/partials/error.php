<?php
declare(strict_types=1);

if (!defined('GOG')) {
    http_response_code(404);
    exit;
}

/**
 * Fehlerseite für Aufrufe ohne JavaScript. Erwartet:
 *   $errorMessage string
 *   $backHref     string
 */
$texts = gog_texts();
?>
<main class="errorbox">
  <h1><?= e((string) $texts['error_page']['title']) ?></h1>
  <p><?= e((string) ($errorMessage ?? $texts['errors']['server'])) ?></p>
  <a class="btn btn-primary" href="<?= e((string) ($backHref ?? './#vorlagen')) ?>"><?= e((string) $texts['error_page']['back']) ?></a>
</main>
