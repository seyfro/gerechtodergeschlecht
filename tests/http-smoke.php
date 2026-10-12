<?php
declare(strict_types=1);

/*
 * HTTP-Test gegen den eingebauten PHP-Server.
 *
 *   php tests/fixtures.php && php tests/http-smoke.php
 *
 * Der Server bekommt eigene Limits mit, weil die lokale php.ini nur 2 MB Upload erlaubt,
 * und einen frischen Laufzeitordner, damit das Rate-Limit bei null beginnt.
 * (In PHP statt PowerShell geschrieben: läuft so auch auf dem Linux-Host.)
 */

$root = dirname(__DIR__);
$docroot = $root . '/gerechtodergeschlecht';
$fixtures = __DIR__ . '/out/fixtures';
$port = (int) (getenv('GOG_TEST_PORT') ?: 8079);
$base = "http://127.0.0.1:{$port}";

if (!is_dir($fixtures)) {
    fwrite(STDERR, "Testfotos fehlen – bitte zuerst: php tests/fixtures.php\n");
    exit(1);
}
if (!extension_loaded('curl')) {
    fwrite(STDERR, "Die curl-Erweiterung wird gebraucht.\n");
    exit(1);
}

$fails = [];
$checks = 0;

function check(string $label, bool $ok, string $detail = ''): void
{
    global $fails, $checks;
    $checks++;
    printf("  [%s] %s%s\n", $ok ? 'ok' : 'FEHLER', $label, $detail !== '' ? "  ({$detail})" : '');
    if (!$ok) {
        $fails[] = $label;
    }
}

/** @return array{status:int,headers:string,body:string} */
function req(string $url, ?array $post = null, array $headers = [], array $files = []): array
{
    $ch = curl_init($url);
    $opts = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_HEADER => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_HTTPHEADER => $headers,
    ];
    if ($post !== null) {
        $fields = $post;
        foreach ($files as $name => $path) {
            $fields[$name] = new CURLFile($path);
        }
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $fields;
    }
    curl_setopt_array($ch, $opts);
    $raw = (string) curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
    $hlen = (int) curl_getinfo($ch, CURLINFO_HEADER_SIZE);
    curl_close($ch);
    return ['status' => $status, 'headers' => substr($raw, 0, $hlen), 'body' => substr($raw, $hlen)];
}

$server = null;
$varDir = null;

function startServer(string $docroot, int $port): array
{
    global $server, $varDir;
    stopServer();
    $varDir = sys_get_temp_dir() . '/gog-smoke-' . bin2hex(random_bytes(4));
    mkdir($varDir, 0775, true);
    $cmd = escapeshellarg(PHP_BINARY) . ' -S 127.0.0.1:' . $port . ' -t ' . escapeshellarg($docroot)
        . ' -d upload_max_filesize=16M -d post_max_size=16M -d memory_limit=512M -d display_errors=0';
    // Nur skalare Umgebungsvariablen weitergeben ($_SERVER enthält auch argv als Array).
    $env = array_filter(getenv(), 'is_string');
    $env['GOG_VAR_DIR'] = $varDir;
    $descriptors = [0 => ['pipe', 'r'], 1 => ['file', $varDir . '/server.log', 'a'], 2 => ['file', $varDir . '/server.log', 'a']];
    $server = proc_open($cmd, $descriptors, $pipes, $docroot, $env);
    if (!is_resource($server)) {
        fwrite(STDERR, "Server konnte nicht gestartet werden.\n");
        exit(1);
    }
    // warten, bis die Startseite antwortet
    for ($i = 0; $i < 100; $i++) {
        usleep(100000);
        $r = req("http://127.0.0.1:{$port}/");
        if ($r['status'] === 200) {
            return $r;
        }
    }
    fwrite(STDERR, "Server antwortet nicht auf Port {$port}.\n");
    stopServer();
    exit(1);
}

function stopServer(): void
{
    global $server;
    if (is_resource($server)) {
        $status = proc_get_status($server);
        if ($status['running'] ?? false) {
            // Der Kindprozess von cmd/sh muss mitgenommen werden.
            if (stripos(PHP_OS_FAMILY, 'Windows') !== false) {
                exec('taskkill /F /T /PID ' . (int) $status['pid'] . ' 2>NUL');
            } else {
                proc_terminate($server, 9);
            }
        }
        proc_close($server);
        $server = null;
    }
}

register_shutdown_function('stopServer');

echo "Server auf {$base}\n\n";
$home = startServer($docroot, $port);

// --- Startseite -------------------------------------------------------------------------
echo "Startseite:\n";
check('GET / liefert 200', $home['status'] === 200);
check('Seite nennt die Vorlagen', str_contains($home['body'], 'id="vorlagen"'));
check('sechs Formulare', substr_count($home['body'], 'class="tpl-form"') === 6);
preg_match('/name="csrf" value="([^"]+)"/', $home['body'], $m);
$csrf = $m[1] ?? '';
check('Formular-Token vorhanden', $csrf !== '');

// --- Erfolgsfall -------------------------------------------------------------------------
echo "\nBildgenerierung:\n";
$ok = req("{$base}/generate.php", ['template' => '2', 'csrf' => $csrf], [], ['photo' => $fixtures . '/portrait.jpg']);
check('gültiger Upload liefert 200', $ok['status'] === 200, (string) $ok['status']);
check('Content-Type ist image/png', (bool) preg_match('~Content-Type:\s*image/png~i', $ok['headers']));
check(
    'Dateiname im Content-Disposition',
    str_contains($ok['headers'], 'filename="wir-vaeter-gerecht-oder-geschlecht-2.png"')
);
check('Cache-Control: no-store', (bool) preg_match('~Cache-Control:\s*no-store~i', $ok['headers']));
check('Antwort beginnt mit der PNG-Signatur', str_starts_with($ok['body'], "\x89PNG\r\n\x1a\n"));
$img = @imagecreatefromstring($ok['body']);
check('PNG ist 1200 x 1500', $img !== false && imagesx($img) === 1200 && imagesy($img) === 1500);

// --- Fehlerfälle -------------------------------------------------------------------------
echo "\nFehlerfälle:\n";
$r = req("{$base}/generate.php", ['template' => '1', 'csrf' => $csrf], [], ['photo' => $fixtures . '/text.txt']);
check('Textdatei statt Foto liefert 415', $r['status'] === 415, (string) $r['status']);

$r = req("{$base}/generate.php", ['template' => '1', 'csrf' => $csrf], [], ['photo' => $fixtures . '/truncated.jpg']);
check('abgeschnittenes JPEG liefert 400', $r['status'] === 400, (string) $r['status']);

$r = req("{$base}/generate.php", ['template' => '9', 'csrf' => $csrf], [], ['photo' => $fixtures . '/square.jpg']);
check('unbekannte Vorlage liefert 400', $r['status'] === 400, (string) $r['status']);
check('ohne JSON-Wunsch kommt eine HTML-Seite', str_contains($r['body'], '<!doctype html>'));

$r = req(
    "{$base}/generate.php",
    ['template' => '9', 'csrf' => $csrf],
    ['Accept: application/json'],
    ['photo' => $fixtures . '/square.jpg']
);
$json = json_decode($r['body'], true);
check('mit Accept: application/json kommt JSON', is_array($json) && ($json['code'] ?? '') === 'bad_template', $r['body']);

$r = req("{$base}/generate.php", ['template' => '1', 'csrf' => 'unsinn'], [], ['photo' => $fixtures . '/square.jpg']);
check('falsches Token liefert 400', $r['status'] === 400, (string) $r['status']);

$r = req("{$base}/generate.php", ['template' => '1', 'csrf' => $csrf]);
check('ohne Foto liefert 400', $r['status'] === 400, (string) $r['status']);

$r = req("{$base}/generate.php");
check('GET auf generate.php liefert 405', $r['status'] === 405, (string) $r['status']);
check('405 nennt Allow: POST', (bool) preg_match('~Allow:\s*POST~i', $r['headers']));

// zu große Datei (13 MB)
$big = sys_get_temp_dir() . '/gog-big.jpg';
file_put_contents($big, file_get_contents($fixtures . '/portrait.jpg') . str_repeat("\0", 13 * 1024 * 1024));
$r = req("{$base}/generate.php", ['template' => '1', 'csrf' => $csrf], [], ['photo' => $big]);
check('13-MB-Datei liefert 413', $r['status'] === 413, (string) $r['status']);
@unlink($big);

// --- Vorschau ----------------------------------------------------------------------------
echo "\nVorschau:\n";
$r = req("{$base}/preview.php?template=3");
check('preview.php liefert 200', $r['status'] === 200, (string) $r['status']);
check('Vorschau ist ein PNG', str_starts_with($r['body'], "\x89PNG\r\n\x1a\n"));
check('Vorschau wird inline ausgeliefert', str_contains($r['headers'], 'Content-Disposition: inline'));
$r = req("{$base}/preview.php?template=99");
check('preview.php lehnt unbekannte Vorlage ab', $r['status'] === 400, (string) $r['status']);

// --- Partials sind nicht direkt aufrufbar -----------------------------------------------
echo "\nDirektaufrufe:\n";
foreach (['partials/card.php', 'config.php', 'templates.php', 'texts.php'] as $path) {
    $r = req("{$base}/{$path}");
    check("{$path} liefert kein verwertbares Ergebnis", $r['status'] === 404 || trim($r['body']) === '', (string) $r['status']);
}

// --- Rate-Limit (frischer Server, damit die Zählung bei null beginnt) --------------------
echo "\nRate-Limit:\n";
$home = startServer($docroot, $port);
preg_match('/name="csrf" value="([^"]+)"/', $home['body'], $m);
$csrf = $m[1] ?? '';
$limit = 10;
$statuses = [];
for ($i = 0; $i < $limit + 1; $i++) {
    $r = req("{$base}/generate.php", ['template' => '2', 'csrf' => $csrf], [], ['photo' => $fixtures . '/square.jpg']);
    $statuses[] = $r['status'];
    $last = $r;
}
check("die ersten {$limit} Anfragen sind erlaubt", array_slice($statuses, 0, $limit) === array_fill(0, $limit, 200), implode(',', $statuses));
check('die 11. Anfrage liefert 429', $last['status'] === 429, (string) $last['status']);
check('429 nennt Retry-After', (bool) preg_match('~Retry-After:\s*\d+~i', $last['headers']));

stopServer();

echo "\n{$checks} Prüfungen, " . count($fails) . " Fehler.\n";
exit($fails === [] ? 0 : 1);
