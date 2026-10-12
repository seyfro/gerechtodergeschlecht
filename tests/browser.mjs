/*
 * Browsertest der Komfort-Ebene (assets/app.js) über das DevTools-Protokoll.
 *
 *   node tests/browser.mjs
 *
 * Startet selbst einen PHP-Server und einen Browser ohne Fenster, prüft Vorschau, Download
 * und die Fehlermeldung bei zu großen Dateien und räumt danach auf.
 * Voraussetzung: node ab Version 22 (eingebaute WebSocket-Unterstützung) und Chrome oder
 * Edge; ein anderer Pfad lässt sich über die Umgebungsvariable CHROME setzen.
 *
 * Getestet wird bewusst die sichtbare Wirkung (berechnetes display), nicht nur die
 * gesetzten Eigenschaften: Eine eigene display-Regel kann das hidden-Attribut aushebeln,
 * und genau das war hier schon einmal der Fall.
 */

import { spawn, execSync } from 'node:child_process';
import fs from 'node:fs';
import os from 'node:os';
import path from 'node:path';
import { fileURLToPath } from 'node:url';

const here = path.dirname(fileURLToPath(import.meta.url));
const root = path.dirname(here);
const docroot = path.join(root, 'gerechtodergeschlecht');
const fixtures = path.join(here, 'out', 'fixtures');
const httpPort = Number(process.env.GOG_TEST_PORT || 8078);
const cdpPort = Number(process.env.GOG_CDP_PORT || 9224);
const tmp = fs.mkdtempSync(path.join(os.tmpdir(), 'gog-browser-'));

if (!fs.existsSync(path.join(fixtures, 'landscape.jpg'))) {
  console.error('Testfotos fehlen – bitte zuerst: php tests/fixtures.php');
  process.exit(1);
}

function findBrowser() {
  const candidates = [
    process.env.CHROME,
    'C:/Program Files/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Google/Chrome/Application/chrome.exe',
    'C:/Program Files (x86)/Microsoft/Edge/Application/msedge.exe',
    '/usr/bin/google-chrome',
    '/usr/bin/chromium',
    '/Applications/Google Chrome.app/Contents/MacOS/Google Chrome',
  ].filter(Boolean);
  return candidates.find((p) => fs.existsSync(p));
}

const browserPath = findBrowser();
if (!browserPath) {
  console.error('Kein Chrome oder Edge gefunden. Pfad über CHROME=... angeben.');
  process.exit(1);
}

const procs = [];
function cleanup() {
  for (const p of procs) {
    try {
      if (process.platform === 'win32') {
        execSync(`taskkill /F /T /PID ${p.pid}`, { stdio: 'ignore' });
      } else {
        process.kill(-p.pid, 'SIGKILL');
      }
    } catch { /* schon beendet */ }
  }
  try { fs.rmSync(tmp, { recursive: true, force: true }); } catch { /* egal */ }
}
process.on('exit', cleanup);

const sleep = (ms) => new Promise((r) => setTimeout(r, ms));

procs.push(spawn(process.env.PHP || 'php', [
  '-S', `127.0.0.1:${httpPort}`, '-t', docroot,
  '-d', 'upload_max_filesize=16M', '-d', 'post_max_size=16M', '-d', 'memory_limit=512M',
], { env: { ...process.env, GOG_VAR_DIR: tmp }, stdio: 'ignore', detached: process.platform !== 'win32' }));

procs.push(spawn(browserPath, [
  '--headless=new', '--disable-gpu', '--no-first-run', '--no-default-browser-check',
  `--remote-debugging-port=${cdpPort}`, `--user-data-dir=${path.join(tmp, 'profile')}`, 'about:blank',
], { stdio: 'ignore', detached: process.platform !== 'win32' }));

async function waitFor(fn, label, tries = 60) {
  for (let i = 0; i < tries; i++) {
    try { const v = await fn(); if (v) return v; } catch { /* noch nicht bereit */ }
    await sleep(300);
  }
  throw new Error(`${label} nicht erreichbar`);
}

await waitFor(async () => (await fetch(`http://127.0.0.1:${httpPort}/`)).ok, 'PHP-Server');
const targets = await waitFor(async () => {
  const list = await (await fetch(`http://127.0.0.1:${cdpPort}/json/list`)).json();
  return list.find((t) => t.type === 'page') || null;
}, 'Browser');

const ws = new WebSocket(targets.webSocketDebuggerUrl);
let msgId = 0;
const pending = new Map();
const send = (method, params = {}) => new Promise((resolve, reject) => {
  const id = ++msgId;
  pending.set(id, { resolve, reject });
  ws.send(JSON.stringify({ id, method, params }));
});
ws.onmessage = (e) => {
  const m = JSON.parse(e.data);
  const slot = m.id && pending.get(m.id);
  if (slot) {
    pending.delete(m.id);
    m.error ? slot.reject(new Error(m.error.message)) : slot.resolve(m.result);
  }
};
await new Promise((r) => { ws.onopen = r; });

await send('Page.enable');
await send('DOM.enable');
await send('Runtime.enable');
await send('Emulation.setDeviceMetricsOverride', { width: 1280, height: 900, deviceScaleFactor: 1, mobile: false });
await send('Page.navigate', { url: `http://127.0.0.1:${httpPort}/` });
await sleep(2500);

const ev = async (expression) => {
  const r = await send('Runtime.evaluate', { expression, returnByValue: true, awaitPromise: true });
  if (r.exceptionDetails) {
    throw new Error(r.exceptionDetails.text + ' ' + (r.exceptionDetails.exception?.description || ''));
  }
  return r.result.value;
};

const failed = [];
const check = (label, ok, detail = '') => {
  console.log(`  [${ok ? 'ok' : 'FEHLER'}] ${label}${detail ? `  (${detail})` : ''}`);
  if (!ok) failed.push(label);
};
const shown = (selector) => ev(`getComputedStyle(document.querySelector('${selector}')).display !== 'none'`);

console.log('Ausgangszustand:');
check('Download-Knopf ist gesperrt', await ev(`document.querySelector('.tpl-form button[type=submit]').disabled`));
check('Vorschaubild ist unsichtbar', !(await shown('.tpl-photo img')));
check('Platzhaltertext ist sichtbar', await shown('.tpl-photo-hint'));

// Den Download abfangen statt ihn zu speichern: So ist prüfbar, was app.js übergibt.
await ev(`window.__caught = {};
  const origClick = HTMLAnchorElement.prototype.click;
  HTMLAnchorElement.prototype.click = function () {
    if (this.download) { window.__caught.name = this.download; return; }
    return origClick.apply(this, arguments);
  };
  const origCreate = URL.createObjectURL;
  URL.createObjectURL = function (obj) {
    if (obj instanceof Blob && obj.type === 'image/png') { window.__caught.size = obj.size; window.__caught.blob = obj; }
    return origCreate.apply(URL, arguments);
  }; true`);

const doc = await send('DOM.getDocument');
const fileInput = await send('DOM.querySelector', { nodeId: doc.root.nodeId, selector: '.tpl-form input[type=file]' });
const setFile = (file) => send('DOM.setFileInputFiles', { nodeId: fileInput.nodeId, files: [path.resolve(file)] });

console.log('\nFoto wählen:');
await setFile(path.join(fixtures, 'landscape.jpg'));
await sleep(700);
check('Vorschaubild wird sichtbar', await shown('.tpl-photo img'));
check('Vorschau nutzt eine lokale Objekt-URL', (await ev(`document.querySelector('.tpl-photo img').src`)).startsWith('blob:'));
check('Platzhaltertext verschwindet wirklich', !(await shown('.tpl-photo-hint')));
check('Vorschau ist schwarz-weiß', (await ev(`getComputedStyle(document.querySelector('.tpl-photo img')).filter`)).includes('grayscale'));
check('Download-Knopf ist frei', !(await ev(`document.querySelector('.tpl-form button[type=submit]').disabled`)));
check('Statuszeile meldet die Auswahl', (await ev(`document.querySelector('.tpl-status').textContent`)) === 'Foto gewählt');

console.log('\nBild herunterladen:');
await ev(`document.querySelector('.tpl-form button[type=submit]').click(); true`);
await sleep(6000);
check('Statuszeile meldet den Download', (await ev(`document.querySelector('.tpl-status').textContent`)) === 'Heruntergeladen', await ev(`document.querySelector('.tpl-status').textContent`));
check('kein Fehlerzustand', !(await ev(`document.querySelector('.tpl-status').classList.contains('is-error')`)));
check('die Seite bleibt stehen', (await ev(`location.pathname`)).endsWith('/'));
check('Dateiname stimmt', (await ev(`window.__caught.name || ''`)) === 'wir-vaeter-gerecht-oder-geschlecht-1.png', await ev(`window.__caught.name || '(keiner)'`));
const size = await ev(`window.__caught.size || 0`);
check('PNG hat eine plausible Größe', size > 50000, `${size} Bytes`);
const sig = await ev(`window.__caught.blob.slice(0,8).arrayBuffer().then(b => [...new Uint8Array(b)].map(x => x.toString(16).padStart(2,'0')).join(''))`);
check('PNG-Signatur stimmt', sig === '89504e470d0a1a0a', sig);

console.log('\nZu große Datei:');
const big = path.join(tmp, 'big.jpg');
fs.writeFileSync(big, Buffer.concat([fs.readFileSync(path.join(fixtures, 'portrait.jpg')), Buffer.alloc(13 * 1024 * 1024)]));
await setFile(big);
await sleep(600);
check('Meldung erscheint sofort', await ev(`document.querySelector('.tpl-status').classList.contains('is-error')`), await ev(`document.querySelector('.tpl-status').textContent`));
check('Download-Knopf bleibt gesperrt', await ev(`document.querySelector('.tpl-form button[type=submit]').disabled`));

console.log(`\n${failed.length} Fehler.`);
ws.close();
cleanup();
process.exit(failed.length ? 1 : 0);
