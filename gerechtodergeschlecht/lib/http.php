<?php
declare(strict_types=1);

/*
 * HTTP-Helfer für generate.php und preview.php: Fehlerantworten (JSON für fetch,
 * HTML-Fehlerseite fürs reine Formular), PNG-Auslieferung, Client-IP.
 */

/** true, wenn der Aufruf per fetch() aus app.js kommt und JSON erwartet. */
function gog_wants_json(): bool
{
    $accept = (string) ($_SERVER['HTTP_ACCEPT'] ?? '');
    $xrw = strtolower((string) ($_SERVER['HTTP_X_REQUESTED_WITH'] ?? ''));
    return str_contains($accept, 'application/json') || $xrw === 'fetch';
}

function gog_client_ip(): string
{
    $header = gog_config('trusted_proxy_header');
    if (is_string($header) && $header !== '' && !empty($_SERVER[$header])) {
        $first = trim(explode(',', (string) $_SERVER[$header])[0]);
        if (filter_var($first, FILTER_VALIDATE_IP) !== false) {
            return $first;
        }
    }
    return (string) ($_SERVER['REMOTE_ADDR'] ?? '0.0.0.0');
}

/**
 * Bricht mit einem Fehler ab. $code ist ein Schlüssel aus texts.php['errors'];
 * $template steuert den Rücklink der HTML-Fehlerseite.
 */
function gog_fail(int $status, string $code, ?int $template = null, ?int $retryAfter = null): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    $texts = gog_texts();
    $message = $texts['errors'][$code] ?? $texts['errors']['server'];

    if (!headers_sent()) {
        http_response_code($status);
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
        if ($retryAfter !== null) {
            header('Retry-After: ' . $retryAfter);
        }
        if ($status === 405) {
            header('Allow: POST');
        }
    }

    if (gog_wants_json()) {
        if (!headers_sent()) {
            header('Content-Type: application/json; charset=utf-8');
        }
        echo json_encode(
            ['ok' => false, 'code' => $code, 'error' => $message],
            JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES
        );
        exit;
    }

    if (!headers_sent()) {
        header('Content-Type: text/html; charset=utf-8');
    }
    $pageTitle = $texts['error_page']['title'] . ' – ' . $texts['meta']['title'];
    $errorMessage = $message;
    $backHref = './' . ($template !== null ? '#vorlage-' . $template : '#vorlagen');
    $noindex = true;
    include GOG_ROOT . '/partials/head.php';
    include GOG_ROOT . '/partials/header.php';
    include GOG_ROOT . '/partials/error.php';
    include GOG_ROOT . '/partials/footer.php';
    exit;
}

/** Liefert PNG-Binärdaten aus; vorher werden alle Output-Buffer verworfen (kein Byte vor dem PNG). */
function gog_send_png(string $png, string $filename, bool $attachment = true, array $extraHeaders = []): never
{
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
    if (headers_sent()) {
        exit;
    }
    header('Content-Type: image/png');
    header('Content-Length: ' . strlen($png));
    header('Content-Disposition: ' . ($attachment ? 'attachment' : 'inline') . '; filename="' . $filename . '"');
    header('X-Content-Type-Options: nosniff');
    if ($attachment) {
        header('Cache-Control: no-store');
        header('X-Robots-Tag: noindex');
    }
    foreach ($extraHeaders as $h) {
        header($h);
    }
    echo $png;
    exit;
}
