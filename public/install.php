<?php
/**
 * ClubKit – Web Installer
 * Version: 1.0.3-dev
 *
 * Upload to: /clubkit/public/install.php
 * Aufruf:    https://deine-domain.de/install.php
 * Reset:     https://deine-domain.de/install.php?reset=1
 *
 * Artisan-Commands laufen über Laravels eigenen Kernel – kein shell_exec nötig.
 * DEV MODE: Installer bleibt nach Installation bestehen.
 * PRODUCTION: @unlink(__FILE__); in Schritt 6.6 aktivieren.
 */

declare(strict_types=1);
session_start();

// ─── Session Reset ────────────────────────────────────────────────────────────
// Wird ausgelöst durch:
//   a) ?reset=1 im URL – immer
//   b) Session sagt "installiert", aber storage/installed existiert nicht
//      (fehlgeschlagene oder abgebrochene Installation)
$_ckMarker = dirname(__DIR__) . '/storage/installed';
if (
    isset($_GET['reset']) ||
    (!empty($_SESSION['ck_installed']) && !file_exists($_ckMarker))
) {
    session_unset();
    session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}
unset($_ckMarker);

// ─────────────────────────────────────────────────────────────────────────────
// Konfiguration
// ─────────────────────────────────────────────────────────────────────────────

const INSTALLER_VERSION = '1.0.3-dev';
const MIN_PHP_VERSION   = '8.3.0';
const REQUIRED_EXT      = ['pdo', 'pdo_mysql', 'mbstring', 'xml', 'curl', 'zip', 'bcmath', 'json', 'openssl'];

const MODULES = [
    'core'      => ['label' => '⚙️ Kern (Auth, Settings, Nutzer)',     'required' => true,  'desc' => 'Pflicht: Login, Nutzerrollen, Rechte, Vereinseinstellungen'],
    'teams'     => ['label' => '🏟️ Teams & Mitglieder',                'required' => false, 'desc' => 'Teams, Spielerinnen/Mitglieder, DFBnet-Import'],
    'fixtures'  => ['label' => '⚽ Spieltage & Aufgaben',               'required' => false, 'desc' => 'Spieltagsverwaltung, Aufgaben-Zuweisung, Rotation, WhatsApp-Export'],
    'training'  => ['label' => '🏋️ Training',                          'required' => false, 'desc' => 'Trainingseinheiten planen und verwalten'],
    'guardians' => ['label' => '👨‍👩‍👧 Erziehungsberechtigte (Jugend)',     'required' => false, 'desc' => 'Eltern/Vormünder verwalten – nur für Jugendvereine'],
    'finances'  => ['label' => '💰 Finanzen',                          'required' => false, 'desc' => 'Einnahmen, Auslagen, Quittungen, Finanzübersicht'],
    'events'    => ['label' => '🎉 Veranstaltungen & Elternabend',      'required' => false, 'desc' => 'Events, Occasionen, Elternabend-Präsentation'],
];

$BASE  = dirname(__DIR__);
$ENV   = $BASE . '/.env';
$BOOT  = $BASE . '/bootstrap/app.php';

// ─────────────────────────────────────────────────────────────────────────────
// Hilfsfunktionen
// ─────────────────────────────────────────────────────────────────────────────

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function e(mixed $v): string  { return h((string)($v ?? '')); }

function systemChecks(string $base): array {
    $checks = [];
    $checks[] = [
        'label' => 'PHP ≥ ' . MIN_PHP_VERSION,
        'ok'    => version_compare(PHP_VERSION, MIN_PHP_VERSION, '>='),
        'value' => PHP_VERSION,
    ];
    foreach (REQUIRED_EXT as $ext) {
        $checks[] = [
            'label' => "ext-$ext",
            'ok'    => extension_loaded($ext),
            'value' => extension_loaded($ext) ? '✓' : '✗ fehlt',
        ];
    }
    foreach ([
        '.env schreibbar'             => [$base,                    true],
        'storage/ schreibbar'         => ["$base/storage",          true],
        'bootstrap/cache/ schreibbar' => ["$base/bootstrap/cache",  true],
        'vendor/ vorhanden'           => ["$base/vendor",           false],
    ] as $lbl => [$path, $needsWrite]) {
        $ok = $needsWrite ? is_writable($path) : is_readable($path);
        $checks[] = ['label' => $lbl, 'ok' => $ok, 'value' => $ok ? '✓ ok' : '✗ Problem'];
    }
    return $checks;
}

function testDb(string $host, int $port, string $db, string $user, string $pass): true|string {
    try {
        new PDO(
            "mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4",
            $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

function buildEnv(array $c, string $existingEnv): string {
    preg_match('/^APP_KEY=(.+)$/m', $existingEnv, $m);
    $key     = trim($m[1] ?? '');
    $appName = addslashes($c['app_name']);
    return <<<ENV
APP_NAME="{$appName}"
APP_ENV=production
APP_KEY={$key}
APP_DEBUG=false
APP_URL={$c['app_url']}
APP_TIMEZONE=Europe/Berlin
APP_LOCALE=de
APP_FALLBACK_LOCALE=en

LOG_CHANNEL=daily
LOG_LEVEL=warning

DB_CONNECTION=mysql
DB_HOST={$c['db_host']}
DB_PORT={$c['db_port']}
DB_DATABASE={$c['db_name']}
DB_USERNAME={$c['db_user']}
DB_PASSWORD={$c['db_pass']}

CACHE_STORE=database
QUEUE_CONNECTION=database
SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=false
SESSION_PATH=/
SESSION_DOMAIN=null

MAIL_MAILER=log

CLUBKIT_MODULES={$c['modules']}
CLUBKIT_YOUTH_CLUB={$c['youth_club']}
ENV;
}

function bootstrapLaravel(string $base): object {
    foreach (['config.php', 'packages.php', 'services.php', 'events.php'] as $f) {
        @unlink("$base/bootstrap/cache/$f");
    }
    require_once "$base/vendor/autoload.php";
    return require "$base/bootstrap/app.php";
}

function artisanCall(object $app, string $command, array $args = []): array {
    try {
        $kernel   = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $exitCode = $kernel->call($command, $args);
        return ['ok' => $exitCode === 0, 'out' => trim($kernel->output())];
    } catch (\Throwable $e) {
        return ['ok' => false, 'out' => get_class($e) . ': ' . $e->getMessage()];
    }
}

function createAdmin(string $name, string $email, string $pass, array $db): true|string {
    try {
        $pdo   = new PDO(
            "mysql:host={$db['host']};port={$db['port']};dbname={$db['name']};charset=utf8mb4",
            $db['user'], $db['pass'],
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
        );
        $check = $pdo->query("SHOW TABLES LIKE 'users'")->fetchAll();
        if (empty($check)) return "Tabelle 'users' nicht gefunden – Migration fehlgeschlagen?";
        $hash = password_hash($pass, PASSWORD_BCRYPT, ['cost' => 12]);
        $now  = date('Y-m-d H:i:s');
        $pdo->prepare(
            "INSERT INTO users (name, email, password, email_verified_at, created_at, updated_at) VALUES (?,?,?,?,?,?)"
        )->execute([$name, $email, $hash, $now, $now, $now]);
        return true;
    } catch (PDOException $e) {
        return $e->getMessage();
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Step-Routing (POST-Handler)
// ─────────────────────────────────────────────────────────────────────────────

$_SESSION['ck_errors'] ??= [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'step1_next') {
        $_SESSION['ck_step'] = 2;
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step2_next') {
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int)($_POST['db_port'] ?? 3306);
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        $errs = [];
        if (!$host) $errs[] = 'DB-Hostname ist Pflicht.';
        if (!$name) $errs[] = 'Datenbankname ist Pflicht.';
        if (!$user) $errs[] = 'Datenbanknutzer ist Pflicht.';
        if (!$errs) {
            $r = testDb($host, $port, $name, $user, $pass);
            if ($r !== true) $errs[] = "Verbindung fehlgeschlagen: $r";
        }
        if ($errs) {
            $_SESSION['ck_errors'] = $errs;
            $_SESSION['ck_step']   = 2;
        } else {
            $_SESSION['ck_data']['db'] = compact('host', 'port', 'name', 'user', 'pass');
            $_SESSION['ck_step'] = 3;
        }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step3_next') {
        $appName   = trim($_POST['app_name'] ?? 'ClubKit') ?: 'ClubKit';
        $appUrl    = rtrim(trim($_POST['app_url'] ?? ''), '/');
        $youthClub = isset($_POST['youth_club']) ? 'true' : 'false';
        if (!filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['ck_errors'] = ['Gültige App-URL erforderlich (inkl. https://).'];
            $_SESSION['ck_step']   = 3;
            header('Location: ' . $_SERVER['PHP_SELF']); exit;
        }
        $_SESSION['ck_data']['app'] = compact('appName', 'appUrl', 'youthClub');
        $_SESSION['ck_step'] = 4;
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step4_next') {
        $name  = trim($_POST['admin_name']  ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $pass  = $_POST['admin_pass']  ?? '';
        $pass2 = $_POST['admin_pass2'] ?? '';
        $errs  = [];
        if (!$name)                                       $errs[] = 'Name ist Pflicht.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL))  $errs[] = 'Gültige E-Mail erforderlich.';
        if (strlen($pass) < 8)                           $errs[] = 'Passwort: mindestens 8 Zeichen.';
        if ($pass !== $pass2)                            $errs[] = 'Passwörter stimmen nicht überein.';
        if ($errs) {
            $_SESSION['ck_errors'] = $errs;
            $_SESSION['ck_step']   = 4;
        } else {
            $_SESSION['ck_data']['admin'] = compact('name', 'email', 'pass');
            $_SESSION['ck_step'] = 5;
        }
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step5_install') {
        $mods = array_keys($_POST['modules'] ?? []);
        if (!in_array('core', $mods)) $mods[] = 'core';
        $_SESSION['ck_data']['modules'] = $mods;
        $_SESSION['ck_step'] = 6;
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }
}

// ─────────────────────────────────────────────────────────────────────────────
// Installation ausführen (Step 6, einmalig pro Session)
// ─────────────────────────────────────────────────────────────────────────────

$step        = (int)($_SESSION['ck_step']   ?? 1);
$errors      = $_SESSION['ck_errors'] ?? [];
$data        = $_SESSION['ck_data']   ?? [];
$installLog  = [];
$installDone = false;

if ($step === 6 && !($_SESSION['ck_installed'] ?? false)) {
    $db  = $data['db']     ?? [];
    $app = $data['app']    ?? [];
    $adm = $data['admin']  ?? [];
    $mod = $data['modules'] ?? ['core'];

    // 6.1 – .env schreiben
    $existing = file_exists($ENV) ? file_get_contents($ENV) : '';
    $newEnv   = buildEnv([
        'app_name'   => $app['appName']   ?? 'ClubKit',
        'app_url'    => $app['appUrl']    ?? 'http://localhost',
        'db_host'    => $db['host']       ?? '127.0.0.1',
        'db_port'    => (string)($db['port'] ?? 3306),
        'db_name'    => $db['name']       ?? '',
        'db_user'    => $db['user']       ?? '',
        'db_pass'    => $db['pass']       ?? '',
        'modules'    => implode(',', $mod),
        'youth_club' => $app['youthClub'] ?? 'false',
    ], $existing);
    $envOk = file_put_contents($ENV, $newEnv) !== false;
    $installLog[] = ['ok' => $envOk, 'msg' => '.env geschrieben', 'detail' => $envOk ? '' : 'Keine Schreibrechte auf ' . $ENV];

    // 6.2 – Laravel bootstrappen (nach .env schreiben!)
    $laravelApp = null;
    if ($envOk) {
        try {
            $laravelApp = bootstrapLaravel($BASE);
            $installLog[] = ['ok' => true, 'msg' => 'Laravel gestartet', 'detail' => ''];
        } catch (\Throwable $e) {
            $installLog[] = ['ok' => false, 'msg' => 'Laravel-Bootstrap fehlgeschlagen', 'detail' => $e->getMessage()];
        }
    }

    // 6.3 – Migrationen
    if ($laravelApp) {
        $migrate = artisanCall($laravelApp, 'migrate', ['--force' => true]);
        $installLog[] = ['ok' => $migrate['ok'], 'msg' => 'Datenbankmigrationen', 'detail' => $migrate['out']];
    } else {
        $installLog[] = ['ok' => false, 'msg' => 'Migrationen übersprungen (Bootstrap fehlgeschlagen)', 'detail' => ''];
    }

    // 6.4 – Admin-Nutzer anlegen
    $adminRes = createAdmin(
        $adm['name']  ?? '',
        $adm['email'] ?? '',
        $adm['pass']  ?? '',
        ['host' => $db['host'], 'port' => $db['port'], 'name' => $db['name'], 'user' => $db['user'], 'pass' => $db['pass']]
    );
    $installLog[] = [
        'ok'     => $adminRes === true,
        'msg'    => 'Admin-Nutzer angelegt',
        'detail' => $adminRes === true ? '' : (string)$adminRes,
    ];

    // 6.5 – Optimieren
    if ($laravelApp) {
        $opt = artisanCall($laravelApp, 'optimize');
        $installLog[] = ['ok' => $opt['ok'], 'msg' => 'App-Cache optimiert', 'detail' => $opt['out']];
    }

    // 6.6 – Installations-Marker setzen
    file_put_contents(
        $BASE . '/storage/installed',
        "Installed: " . date('Y-m-d H:i:s') . "\nModules: " . implode(',', $mod) . "\nVersion: " . INSTALLER_VERSION . "\n"
    );
    $installLog[] = ['ok' => true, 'msg' => 'ClubKit installiert ✅', 'detail' => ''];

    $_SESSION['ck_installed']   = true;
    $_SESSION['ck_install_log'] = $installLog;

    // PRODUCTION: @unlink(__FILE__);
}

if ($step === 6) {
    $installLog  = $_SESSION['ck_install_log'] ?? [];
    $installDone = $_SESSION['ck_installed']   ?? false;
}

$sysChecks = systemChecks($BASE);
$allOk     = array_reduce($sysChecks, fn($c, $i) => $c && $i['ok'], true);
$appUrl    = $data['app']['appUrl'] ?? '';

?>
<!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ClubKit – Installer <?= INSTALLER_VERSION ?></title>
<style>
  *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }
  body { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; background: #f1f5f9; color: #1e293b; min-height: 100vh; display: flex; flex-direction: column; align-items: center; padding: 32px 16px; }
  .card { background: #fff; border-radius: 16px; box-shadow: 0 4px 24px rgba(0,0,0,0.08); width: 100%; max-width: 620px; overflow: hidden; }
  .card-header { background: #0a1628; color: #fff; padding: 24px 28px; }
  .card-header h1 { font-size: 22px; font-weight: 800; letter-spacing: -0.5px; }
  .card-header p  { font-size: 13px; color: #94a3b8; margin-top: 4px; }
  .steps { display: flex; background: #f8fafc; border-bottom: 1px solid #e2e8f0; padding: 0 28px; overflow-x: auto; scrollbar-width: none; }
  .step  { padding: 12px 10px; font-size: 11px; font-weight: 700; color: #94a3b8; white-space: nowrap; border-bottom: 2px solid transparent; }
  .step.active { color: #1a6fc4; border-bottom-color: #1a6fc4; }
  .step.done   { color: #16a34a; }
  .body { padding: 28px; }
  h2 { font-size: 18px; font-weight: 700; margin-bottom: 6px; }
  .subtitle { font-size: 13px; color: #64748b; margin-bottom: 20px; }
  .field { margin-bottom: 16px; }
  label { display: block; font-size: 11px; font-weight: 700; color: #64748b; text-transform: uppercase; letter-spacing: 0.5px; margin-bottom: 5px; }
  input[type=text], input[type=url], input[type=email], input[type=password], input[type=number] {
    width: 100%; padding: 10px 12px; border: 1.5px solid #e2e8f0; border-radius: 8px; font-size: 14px; outline: none; transition: border-color .15s;
  }
  input:focus { border-color: #1a6fc4; }
  .grid2 { display: grid; grid-template-columns: 2fr 1fr; gap: 12px; }
  .btn { display: inline-block; padding: 11px 22px; border-radius: 9px; font-size: 14px; font-weight: 700; cursor: pointer; border: none; transition: background .15s; text-decoration: none; }
  .btn-primary { background: #1a6fc4; color: #fff; }
  .btn-primary:hover { background: #1558a0; }
  .btn-primary:disabled { background: #94a3b8; cursor: not-allowed; }
  .btn-success { background: #16a34a; color: #fff; }
  .btn-reset { background: #f1f5f9; color: #64748b; font-size: 13px; padding: 8px 14px; }
  .btn-reset:hover { background: #e2e8f0; }
  .errors { background: #fef2f2; border: 1.5px solid #fca5a5; border-radius: 10px; padding: 12px 14px; margin-bottom: 18px; }
  .errors p { font-size: 13px; color: #991b1b; margin-bottom: 2px; }
  .check-row { display: flex; align-items: center; gap: 10px; padding: 8px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
  .check-row:last-child { border-bottom: none; }
  .badge { padding: 2px 8px; border-radius: 6px; font-size: 11px; font-weight: 700; flex-shrink: 0; }
  .badge-ok  { background: #dcfce7; color: #166534; }
  .badge-err { background: #fee2e2; color: #991b1b; }
  .badge-req { background: #dbeafe; color: #1e40af; }
  .module-card { border: 1.5px solid #e2e8f0; border-radius: 10px; padding: 12px 14px; margin-bottom: 10px; }
  .module-card:has(input:checked) { border-color: #1a6fc4; background: #eff6ff; }
  .module-card input { margin-right: 10px; accent-color: #1a6fc4; width: 16px; height: 16px; cursor: pointer; }
  .module-label { font-weight: 700; font-size: 14px; }
  .module-desc  { font-size: 12px; color: #64748b; margin-top: 3px; margin-left: 26px; }
  .log-line { display: flex; align-items: flex-start; gap: 10px; padding: 9px 0; border-bottom: 1px solid #f1f5f9; font-size: 13px; }
  .log-line:last-child { border-bottom: none; }
  .log-detail { font-size: 11px; color: #64748b; margin-top: 4px; font-family: monospace; white-space: pre-wrap; word-break: break-all; background: #f8fafc; padding: 6px 8px; border-radius: 6px; max-height: 120px; overflow-y: auto; }
  .info-box { background: #eff6ff; border: 1px solid #bfdbfe; border-radius: 10px; padding: 12px 14px; margin-bottom: 16px; font-size: 13px; color: #1e40af; line-height: 1.6; }
  .success-banner { background: #f0fdf4; border: 1.5px solid #86efac; border-radius: 12px; padding: 18px; margin-bottom: 20px; text-align: center; }
  .success-banner h3 { font-size: 18px; font-weight: 800; color: #166534; }
  .success-banner p  { font-size: 13px; color: #166534; margin-top: 5px; }
  .footer { margin-top: 16px; font-size: 11px; color: #94a3b8; text-align: center; }
  code { background: #f1f5f9; padding: 2px 6px; border-radius: 4px; font-size: 12px; font-family: monospace; }
</style>
</head>
<body>

<div class="card">
  <div class="card-header">
    <h1>⚙️ ClubKit Installer</h1>
    <p>Version <?= INSTALLER_VERSION ?> &nbsp;·&nbsp; PHP <?= PHP_VERSION ?> &nbsp;·&nbsp; <a href="?reset=1" style="color:#64748b;font-size:12px">↺ Neu starten</a></p>
  </div>

  <div class="steps">
    <?php
    $labels = ['1 System', '2 Datenbank', '3 App', '4 Admin', '5 Module', '6 Installation'];
    foreach ($labels as $i => $lbl) {
        $n   = $i + 1;
        $cls = $n < $step ? 'done' : ($n === $step ? 'active' : '');
        echo "<div class=\"step $cls\">" . h($lbl) . "</div>";
    }
    ?>
  </div>

  <div class="body">

  <?php if ($errors): ?>
    <div class="errors">
      <?php foreach ($errors as $err): ?><p>⚠️ <?= h($err) ?></p><?php endforeach; ?>
    </div>
  <?php endif; ?>

  <!-- ── STEP 1: System Check ── -->
  <?php if ($step === 1): ?>
    <h2>System-Check</h2>
    <p class="subtitle">Anforderungen für ClubKit prüfen.</p>
    <?php foreach ($sysChecks as $c): ?>
      <div class="check-row">
        <span class="badge <?= $c['ok'] ? 'badge-ok' : 'badge-err' ?>"><?= $c['ok'] ? 'OK' : 'FEHLER' ?></span>
        <span style="flex:1"><?= h($c['label']) ?></span>
        <span style="font-size:12px;color:#64748b"><?= h($c['value']) ?></span>
      </div>
    <?php endforeach; ?>
    <?php if (!$allOk): ?>
      <p style="font-size:12px;color:#991b1b;margin-top:12px">⚠️ Bitte alle Fehler beheben.</p>
    <?php endif; ?>
    <form method="POST" style="margin-top:20px">
      <input type="hidden" name="action" value="step1_next">
      <button class="btn btn-primary" type="submit" <?= !$allOk ? 'disabled' : '' ?>>Weiter →</button>
    </form>

  <!-- ── STEP 2: Datenbank ── -->
  <?php elseif ($step === 2): ?>
    <h2>Datenbankverbindung</h2>
    <p class="subtitle">MySQL-Zugangsdaten aus ISPConfig. Verbindung wird live getestet.</p>
    <form method="POST">
      <input type="hidden" name="action" value="step2_next">
      <div class="grid2">
        <div class="field">
          <label>DB-Hostname</label>
          <input type="text" name="db_host" value="<?= e($data['db']['host'] ?? '127.0.0.1') ?>" required>
        </div>
        <div class="field">
          <label>Port</label>
          <input type="number" name="db_port" value="<?= e($data['db']['port'] ?? 3306) ?>" required>
        </div>
      </div>
      <div class="field">
        <label>Datenbankname</label>
        <input type="text" name="db_name" value="<?= e($data['db']['name'] ?? '') ?>" placeholder="client_1_clubkit" required>
      </div>
      <div class="field">
        <label>Datenbanknutzer</label>
        <input type="text" name="db_user" value="<?= e($data['db']['user'] ?? '') ?>" placeholder="client_1_user" required>
      </div>
      <div class="field">
        <label>Passwort</label>
        <input type="password" name="db_pass" placeholder="••••••••">
      </div>
      <button class="btn btn-primary" type="submit">Verbindung testen & weiter →</button>
    </form>

  <!-- ── STEP 3: App-Konfiguration ── -->
  <?php elseif ($step === 3): ?>
    <h2>App-Konfiguration</h2>
    <p class="subtitle">Grundeinstellungen für diese Installation.</p>
    <form method="POST">
      <input type="hidden" name="action" value="step3_next">
      <div class="field">
        <label>Vereinsname / App-Name</label>
        <input type="text" name="app_name" value="<?= e($data['app']['appName'] ?? 'ClubKit') ?>" required>
      </div>
      <div class="field">
        <label>App-URL (inkl. https://)</label>
        <input type="url" name="app_url" value="<?= e($data['app']['appUrl'] ?? 'https://') ?>" placeholder="https://orga.meinverein.de" required>
      </div>
      <div class="module-card" style="cursor:pointer">
        <label style="display:flex;align-items:center;cursor:pointer;text-transform:none;letter-spacing:0;font-size:14px;font-weight:700;color:#1e293b">
          <input type="checkbox" name="youth_club" <?= ($data['app']['youthClub'] ?? 'true') === 'true' ? 'checked' : '' ?>>
          👶 Jugendverein / Jugendmannschaft
        </label>
        <p class="module-desc">Aktiviert Elternverwaltung systemweit (nur für Jugendvereine).</p>
      </div>
      <button class="btn btn-primary" type="submit" style="margin-top:12px">Weiter →</button>
    </form>

  <!-- ── STEP 4: Admin-Account ── -->
  <?php elseif ($step === 4): ?>
    <h2>Administrator-Account</h2>
    <p class="subtitle">Erster Nutzer mit vollen Systemrechten.</p>
    <form method="POST">
      <input type="hidden" name="action" value="step4_next">
      <div class="field">
        <label>Vollständiger Name</label>
        <input type="text" name="admin_name" value="<?= e($data['admin']['name'] ?? '') ?>" placeholder="Max Mustermann" required>
      </div>
      <div class="field">
        <label>E-Mail</label>
        <input type="email" name="admin_email" value="<?= e($data['admin']['email'] ?? '') ?>" placeholder="admin@meinverein.de" required>
      </div>
      <div class="field">
        <label>Passwort (min. 8 Zeichen)</label>
        <input type="password" name="admin_pass" required>
      </div>
      <div class="field">
        <label>Passwort wiederholen</label>
        <input type="password" name="admin_pass2" required>
      </div>
      <button class="btn btn-primary" type="submit">Weiter →</button>
    </form>

  <!-- ── STEP 5: Module ── -->
  <?php elseif ($step === 5): ?>
    <h2>Module aktivieren</h2>
    <p class="subtitle">Wähle welche Module installiert werden.</p>
    <form method="POST">
      <input type="hidden" name="action" value="step5_install">
      <?php foreach (MODULES as $key => $mod): ?>
        <div class="module-card">
          <label style="display:flex;align-items:center;cursor:pointer;text-transform:none;letter-spacing:0;font-size:14px;color:#1e293b">
            <input type="checkbox" name="modules[<?= h($key) ?>]" value="1"
              <?= $mod['required'] ? 'checked disabled' : (in_array($key, $data['modules'] ?? array_keys(MODULES)) ? 'checked' : '') ?>>
            <span class="module-label"><?= h($mod['label']) ?></span>
            <?php if ($mod['required']): ?>
              <span class="badge badge-req" style="margin-left:8px">Pflicht</span>
            <?php endif; ?>
          </label>
          <p class="module-desc"><?= h($mod['desc']) ?></p>
        </div>
      <?php endforeach; ?>
      <div class="info-box" style="margin-top:8px">
        ℹ️ Version <?= INSTALLER_VERSION ?>: Kern-Migrationen werden installiert. Weitere Modul-Migrations folgen je Sprint.
      </div>
      <button class="btn btn-primary" type="submit">Jetzt installieren →</button>
    </form>

  <!-- ── STEP 6: Installation ── -->
  <?php elseif ($step === 6): ?>
    <h2>Installation</h2>
    <?php if ($installDone):
      $allGood = array_reduce($installLog, fn($c, $l) => $c && $l['ok'], true); ?>
      <div class="success-banner">
        <?php if ($allGood): ?>
          <h3>🎉 Installation erfolgreich!</h3>
          <p>ClubKit ist einsatzbereit.</p>
        <?php else: ?>
          <h3>⚠️ Abgeschlossen mit Fehlern</h3>
          <p>Bitte Log prüfen, dann <a href="?reset=1">neu starten</a>.</p>
        <?php endif; ?>
      </div>
    <?php endif; ?>

    <?php foreach ($installLog as $log): ?>
      <div class="log-line">
        <span class="badge <?= $log['ok'] ? 'badge-ok' : 'badge-err' ?>"><?= $log['ok'] ? 'OK' : 'FEHLER' ?></span>
        <div style="flex:1">
          <div><?= h($log['msg']) ?></div>
          <?php if (!empty($log['detail'])): ?>
            <div class="log-detail"><?= h($log['detail']) ?></div>
          <?php endif; ?>
        </div>
      </div>
    <?php endforeach; ?>

    <div style="margin-top:20px;display:flex;gap:10px;flex-wrap:wrap;align-items:center">
      <?php if ($installDone && $appUrl): ?>
        <a href="<?= h($appUrl) ?>" class="btn btn-success">🚀 ClubKit öffnen</a>
        <a href="<?= h($appUrl) ?>/login" class="btn btn-primary">🔑 Zum Login</a>
      <?php endif; ?>
      <a href="?reset=1" class="btn btn-reset">↺ Neu installieren</a>
    </div>

  <?php endif; ?>
  </div>
</div>

<div class="footer">ClubKit Installer <?= INSTALLER_VERSION ?></div>
</body>
</html>
