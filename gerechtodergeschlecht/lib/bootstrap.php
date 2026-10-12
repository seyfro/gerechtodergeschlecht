<?php
declare(strict_types=1);

/*
 * Gemeinsamer Einstieg für index.php, generate.php, preview.php sowie Tests und Tools.
 * Gibt nichts aus und sendet keine Header – das bleibt den Aufrufern überlassen.
 */
if (!defined('GOG')) {
    define('GOG', 1);
}
// Ein zweiter Aufruf darf nichts kaputt machen: Werkzeuge laden diese Datei mitunter selbst
// und binden danach noch index.php ein.
if (defined('GOG_ROOT')) {
    return;
}
define('GOG_ROOT', dirname(__DIR__));

mb_internal_encoding('UTF-8');

require_once GOG_ROOT . '/lib/Backend.php';
require_once GOG_ROOT . '/lib/GdBackend.php';
require_once GOG_ROOT . '/lib/ImagickBackend.php';
require_once GOG_ROOT . '/lib/TextLayout.php';
require_once GOG_ROOT . '/lib/Renderer.php';
require_once GOG_ROOT . '/lib/Upload.php';
require_once GOG_ROOT . '/lib/security.php';
require_once GOG_ROOT . '/lib/http.php';

/** Konfiguration (config.php, optional überschrieben durch config.local.php). */
function gog_config(?string $key = null): mixed
{
    static $config = null;
    if ($config === null) {
        $config = require GOG_ROOT . '/config.php';
        $local = GOG_ROOT . '/config.local.php';
        if (is_file($local)) {
            $config = array_replace($config, require $local);
        }
    }
    return $key === null ? $config : ($config[$key] ?? null);
}

function gog_templates(): array
{
    static $templates = null;
    return $templates ??= require GOG_ROOT . '/templates.php';
}

function gog_texts(): array
{
    static $texts = null;
    return $texts ??= require GOG_ROOT . '/texts.php';
}

/** HTML-Escaper für jede Ausgabe. */
function e(?string $s): string
{
    return htmlspecialchars((string) $s, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Beschreibbares Laufzeitverzeichnis: bevorzugt var/ im Projekt, sonst ein Ordner im
 * System-Temp. Unterordner werden bei Bedarf angelegt.
 */
function gog_var_dir(string $sub = ''): string
{
    static $base = null;
    if ($base === null) {
        $candidates = [
            (string) gog_config('var_dir'),
            rtrim(sys_get_temp_dir(), '/\\') . '/gog-' . substr(md5(GOG_ROOT), 0, 12),
        ];
        foreach ($candidates as $dir) {
            if ($dir === '') {
                continue;
            }
            if (!is_dir($dir)) {
                @mkdir($dir, 0775, true);
            }
            if (is_dir($dir) && is_writable($dir)) {
                $base = $dir;
                break;
            }
        }
        if ($base === null) {
            throw new RuntimeException('Kein beschreibbares var-Verzeichnis gefunden');
        }
    }
    if ($sub === '') {
        return $base;
    }
    $dir = $base . '/' . $sub;
    if (!is_dir($dir)) {
        @mkdir($dir, 0775, true);
    }
    return $dir;
}

/** Cache-Buster für Assets: ?v=<mtime>, wie in den anderen Projekten. */
function gog_asset(string $relative): string
{
    $file = GOG_ROOT . '/' . $relative;
    $v = is_file($file) ? (string) filemtime($file) : '0';
    return $relative . '?v=' . $v;
}
