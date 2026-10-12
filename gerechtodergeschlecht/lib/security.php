<?php
declare(strict_types=1);

/*
 * Formular-Token und Rate-Limit ohne Session und ohne Datenbank.
 *
 * Token: "<zeitstempel>.<hmac_sha256(zeitstempel, secret)>" – stateless, damit weder ein
 * Cookie noch Seiten-Caching Probleme machen. Gültig für token_ttl Sekunden.
 * Rate-Limit: pro IP eine kleine Datei mit Zeitstempeln (flock). IPs werden nur als HMAC
 * abgelegt, nie im Klartext. Wenn der Speicher nicht schreibbar ist, wird NICHT blockiert
 * (fail open) – ein defektes var/ darf die Generierung nicht lahmlegen.
 */

/** Geheimnis für HMACs; wird beim ersten Aufruf erzeugt und in var/secret.php abgelegt. */
function gog_secret(): string
{
    static $secret = null;
    if ($secret !== null) {
        return $secret;
    }
    $file = null;
    try {
        $file = gog_var_dir() . '/secret.php';
        if (is_file($file)) {
            $raw = (string) file_get_contents($file);
            if (preg_match('~// ([a-f0-9]{64})~', $raw, $m)) {
                return $secret = $m[1];
            }
        }
    } catch (Throwable $e) {
        error_log('gog: var-Verzeichnis nicht nutzbar: ' . $e->getMessage());
    }
    $secret = bin2hex(random_bytes(32));
    // Als PHP-Datei mit exit; – selbst wenn var/ per Web erreichbar wäre, liefert sie nichts aus.
    if ($file === null || @file_put_contents($file, "<?php exit; // {$secret}\n", LOCK_EX) === false) {
        error_log('gog: secret.php nicht schreibbar – Formular-Token gelten nur für diesen Request');
    }
    return $secret;
}

function gog_form_token(): string
{
    $ts = (string) time();
    return $ts . '.' . hash_hmac('sha256', $ts, gog_secret());
}

function gog_form_token_valid(mixed $token): bool
{
    if (!is_string($token) || !preg_match('/^(\d{1,12})\.([a-f0-9]{64})$/', $token, $m)) {
        return false;
    }
    $age = time() - (int) $m[1];
    if ($age < -60 || $age > (int) gog_config('token_ttl')) {
        return false;
    }
    return hash_equals(hash_hmac('sha256', $m[1], gog_secret()), $m[2]);
}

/** IPv4 komplett, IPv6 auf das /64-Präfix reduziert (ein Haushalt = ein Bucket). */
function gog_rate_bucket(string $ip): string
{
    $bin = @inet_pton($ip);
    if ($bin !== false && strlen($bin) === 16) {
        return 'v6:' . bin2hex(substr($bin, 0, 8));
    }
    return 'v4:' . $ip;
}

/**
 * Zählt einen Versuch. Rückgabe 0 = erlaubt, sonst Sekunden bis zum nächsten erlaubten Versuch.
 */
function gog_rate_limit(string $ip, int $limit, int $window): int
{
    try {
        $dir = gog_var_dir('ratelimit');
    } catch (Throwable $e) {
        error_log('gog: Rate-Limit ohne Speicher (' . $e->getMessage() . ')');
        return 0;
    }
    $file = $dir . '/' . hash_hmac('sha256', gog_rate_bucket($ip), gog_secret());
    $now = time();

    $fh = @fopen($file, 'c+');
    if ($fh === false) {
        error_log('gog: Rate-Limit-Datei nicht schreibbar');
        return 0;
    }
    try {
        if (!flock($fh, LOCK_EX)) {
            return 0;
        }
        $raw = trim((string) stream_get_contents($fh));
        $stamps = [];
        foreach ($raw === '' ? [] : explode("\n", $raw) as $line) {
            $t = (int) $line;
            if ($t > $now - $window) {
                $stamps[] = $t;
            }
        }
        if (count($stamps) >= $limit) {
            return max(1, min($stamps) + $window - $now);
        }
        $stamps[] = $now;
        ftruncate($fh, 0);
        rewind($fh);
        fwrite($fh, implode("\n", $stamps) . "\n");
        fflush($fh);
    } finally {
        flock($fh, LOCK_UN);
        fclose($fh);
    }

    // Gelegentlich alte Buckets wegräumen, damit var/ratelimit nicht wächst.
    if (random_int(1, 50) === 1) {
        gog_rate_limit_gc($dir, $window * 2);
    }
    return 0;
}

function gog_rate_limit_gc(string $dir, int $maxAge): void
{
    $cutoff = time() - $maxAge;
    foreach (glob($dir . '/*') ?: [] as $f) {
        if (is_file($f) && (int) filemtime($f) < $cutoff) {
            @unlink($f);
        }
    }
}
