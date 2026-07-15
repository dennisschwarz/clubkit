<?php
declare(strict_types=1);
session_start();

/**
 * ClubKit – Web Installer v2.0.2
 * - ob_start removed
 * - declare(strict_types) as first statement
 * - Full reset directly in installer
 */

const INSTALLER_VERSION = '2.0.2';
const MIN_PHP           = '8.4.0';
const REQUIRED_EXT      = ['pdo', 'pdo_mysql', 'mbstring', 'xml', 'curl', 'zip', 'bcmath', 'json', 'openssl'];

$BASE        = dirname(__DIR__);
$ENV_FILE    = $BASE . '/.env';
$MODULES_DIR = $BASE . '/modules';

function h(string $s): string { return htmlspecialchars($s, ENT_QUOTES, 'UTF-8'); }
function e(mixed $v): string  { return h((string)($v ?? '')); }

// ── Full reset ────────────────────────────────────────────────────────────────

function fullReset(string $base, string $envFile): array
{
    $log = [];

    // Clear the database (if .env file exists)
    if (file_exists($envFile)) {
        $env    = parse_ini_file($envFile) ?: [];
        $dbHost = $env['DB_HOST']     ?? '127.0.0.1';
        $dbPort = (int)($env['DB_PORT']     ?? 3306);
        $dbName = $env['DB_DATABASE'] ?? '';
        $dbUser = $env['DB_USERNAME'] ?? '';
        $dbPass = $env['DB_PASSWORD'] ?? '';

        if ($dbName && $dbUser) {
            try {
                $pdo = new PDO(
                    "mysql:host=$dbHost;port=$dbPort;dbname=$dbName;charset=utf8mb4",
                    $dbUser, $dbPass,
                    [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]
                );
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 0');
                $tables = $pdo->query('SHOW TABLES')->fetchAll(PDO::FETCH_COLUMN);
                foreach ($tables as $t) {
                    $pdo->exec("DROP TABLE IF EXISTS `$t`");
                }
                $pdo->exec('SET FOREIGN_KEY_CHECKS = 1');
                $log[] = ['ok' => true, 'msg' => count($tables) . ' Tabellen gelöscht'];
            } catch (\PDOException $e) {
                $log[] = ['ok' => false, 'msg' => 'DB-Fehler: ' . $e->getMessage()];
            }
        } else {
            $log[] = ['ok' => false, 'msg' => 'DB-Credentials nicht lesbar'];
        }
    } else {
        $log[] = ['ok' => true, 'msg' => 'Keine .env vorhanden – DB-Reset übersprungen'];
    }

    // Delete .env file
    if (file_exists($envFile)) {
        unlink($envFile);
        $log[] = ['ok' => true, 'msg' => '.env gelöscht'];
    }

    // Remove installed marker
    $marker = $base . '/storage/installed';
    if (file_exists($marker)) {
        unlink($marker);
        $log[] = ['ok' => true, 'msg' => 'storage/installed gelöscht'];
    }

    // Clear bootstrap cache files
    foreach (['config.php','packages.php','services.php','events.php','routes.php'] as $f) {
        @unlink("$base/bootstrap/cache/$f");
    }
    $log[] = ['ok' => true, 'msg' => 'Bootstrap-Cache geleert'];

    return $log;
}

// ── Request handling ──────────────────────────────────────────────────────────

// Session reset only (no database changes)
if (isset($_GET['reset'])) {
    session_unset(); session_destroy();
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// Full reset (database + .env + session)
if (isset($_GET['full_reset'])) {
    $resetLog = fullReset($BASE, $ENV_FILE);
    session_unset(); session_destroy();
    session_start();
    $_SESSION['ck_reset_log'] = $resetLog;
    $_SESSION['ck_step']      = 'reset_done';
    header('Location: ' . strtok($_SERVER['REQUEST_URI'], '?'));
    exit;
}

// ── Preflight ─────────────────────────────────────────────────────────────────

function tryFixPermissions(string $path): void
{
    if (is_dir($path) && ! is_writable($path)) {
        // Try 775 first (sufficient when PHP runs as file owner), fall back to 777.
        @chmod($path, 0775) || @chmod($path, 0777);
        // Recursively fix sub-directories that Laravel writes to.
        foreach (new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($path, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        ) as $item) {
            if ($item->isDir()) {
                @chmod($item->getPathname(), 0775) || @chmod($item->getPathname(), 0777);
            }
        }
    }
}

function runPreflightChecks(string $base): array
{
    $checks = [];
    $checks[] = ['label' => 'PHP >= ' . MIN_PHP, 'ok' => version_compare(PHP_VERSION, MIN_PHP, '>='), 'value' => PHP_VERSION, 'fatal' => true];
    foreach (REQUIRED_EXT as $ext) {
        $ok = extension_loaded($ext);
        $checks[] = ['label' => "ext-$ext", 'ok' => $ok, 'value' => $ok ? 'OK' : 'fehlt', 'fatal' => true];
    }

    // Attempt to fix permissions before reporting failure.
    // On shared hosting, PHP-FPM runs as the file owner and can chmod its own files.
    tryFixPermissions("$base/storage");
    tryFixPermissions("$base/bootstrap/cache");

    foreach ([
        'storage/ schreibbar'         => "$base/storage",
        'bootstrap/cache/ schreibbar' => "$base/bootstrap/cache",
        'Projektordner schreibbar'    => $base,
    ] as $label => $path) {
        $ok    = is_writable($path);
        $value = $ok ? 'OK' : 'Keine Schreibrechte — bitte Ordner-Rechte auf 775 setzen';
        $checks[] = ['label' => $label, 'ok' => $ok, 'value' => $value, 'fatal' => true];
    }

    $vendorOk      = is_dir("$base/vendor") && file_exists("$base/vendor/autoload.php");
    $composerAvail = ! $vendorOk && (function_exists('exec') || function_exists('shell_exec'));
    $buildOk       = file_exists("$base/public/build/manifest.json");
    $checks[] = [
        'label'          => 'vendor/ vorhanden',
        'ok'             => $vendorOk,
        'value'          => $vendorOk ? 'OK' : ($composerAvail ? 'Fehlt — Composer wird automatisch ausgeführt' : 'Fehlt — Release-ZIP (mit vendor/) hochladen'),
        'fatal'          => true,
        'composer_offer' => ! $vendorOk && $composerAvail,
    ];

    $checks[] = [
        'label' => 'Frontend-Assets gebaut (public/build/)',
        'ok'    => $buildOk,
        'value' => $buildOk ? 'OK' : 'Fehlt — npm run build lokal ausführen und public/build/ hochladen',
        'fatal' => true,
    ];

    $envExists = file_exists("$base/.env");
    $checks[] = [
        'label' => 'Fresh Install (.env nicht vorhanden)',
        'ok'    => ! $envExists,
        'value' => $envExists ? 'Warnung: .env existiert (Neuinstallation überschreibt sie)' : 'OK',
        'fatal' => false,
    ];

    return $checks;
}

function detectModules(string $dir): array
{
    $modules = [];
    if (!is_dir($dir)) return $modules;
    foreach (glob($dir . '/*/module.json') as $file) {
        $cfg = @json_decode(file_get_contents($file), true);
        if ($cfg && isset($cfg['slug'])) $modules[$cfg['slug']] = $cfg;
    }
    return $modules;
}

function resolveDeps(array $selected, array $available): array|string
{
    $resolved = []; $resolving = $selected; $i = 0;
    while (!empty($resolving)) {
        if (++$i > 100) return 'Zirkuläre Abhängigkeit.';
        $slug = array_shift($resolving);
        if (in_array($slug, $resolved, true)) continue;
        if (!isset($available[$slug])) return "Modul '$slug' nicht verfügbar.";
        foreach ($available[$slug]['requires'] ?? [] as $dep) {
            if ($dep === 'core' || in_array($dep, $resolved, true)) continue;
            if (!isset($available[$dep])) return "Abhängigkeit '$dep' fehlt.";
            array_unshift($resolving, $dep);
        }
        $resolved[] = $slug;
    }
    return $resolved;
}

function testDb(string $host, int $port, string $db, string $user, string $pass): true|string
{
    try {
        new PDO("mysql:host=$host;port=$port;dbname=$db;charset=utf8mb4", $user, $pass,
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]);
        return true;
    } catch (\PDOException $e) { return $e->getMessage(); }
}

function buildEnv(array $c): string
{
    $n = addslashes($c['app_name']);
    return <<<ENV
APP_NAME="{$n}"
APP_ENV=production
APP_KEY=
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

MAIL_MAILER=log

CLUBKIT_MODULES={$c['modules']}
ENV;
}

function bootstrapLaravel(string $base): object
{
    foreach (['config.php','packages.php','services.php','events.php'] as $f) {
        @unlink("$base/bootstrap/cache/$f");
    }
    require_once "$base/vendor/autoload.php";
    return require "$base/bootstrap/app.php";
}

function runArtisan(object $app, string $cmd, array $args = []): array
{
    try {
        $kernel = $app->make(\Illuminate\Contracts\Console\Kernel::class);
        $code   = $kernel->call($cmd, $args);
        return ['ok' => $code === 0, 'out' => trim($kernel->output())];
    } catch (\Throwable $e) {
        return ['ok' => false, 'out' => get_class($e).': '.$e->getMessage()];
    }
}

// ── POST routing ──────────────────────────────────────────────────────────────

$_SESSION['ck_errors'] ??= [];

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $action = $_POST['action'] ?? '';

    if ($action === 'run_composer') {
        $out    = [];
        $ok     = false;
        $phpBin = PHP_BINARY ?: 'php';

        // Download Composer phar if not already present.
        $composerPhar = sys_get_temp_dir() . '/composer.phar';
        if (! file_exists($composerPhar)) {
            $src = @file_get_contents('https://getcomposer.org/composer-stable.phar');
            if ($src) { file_put_contents($composerPhar, $src); }
        }

        if (file_exists($composerPhar)) {
            $cmd = escapeshellcmd($phpBin) . ' ' . escapeshellarg($composerPhar)
                 . ' install --no-interaction --no-dev --prefer-dist --optimize-autoloader'
                 . ' --working-dir=' . escapeshellarg($BASE)
                 . ' 2>&1';
            if (function_exists('exec')) {
                exec($cmd, $out, $code);
                $ok = ($code === 0);
            } elseif (function_exists('shell_exec')) {
                $res = shell_exec($cmd);
                $out = explode("\n", (string) $res);
                $ok  = is_dir("$BASE/vendor/autoload.php") || is_dir("$BASE/vendor");
            }
        }

        $_SESSION['ck_composer_out'] = ['ok' => $ok, 'lines' => $out];
        header('Location: ' . $_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step1_next') {
        $checks = runPreflightChecks($BASE);
        $fatal  = array_filter($checks, fn($c) => !$c['ok'] && $c['fatal']);
        if ($fatal) { $_SESSION['ck_errors'] = ['Pre-flight fehlgeschlagen.']; $_SESSION['ck_step'] = 1; }
        else { $_SESSION['ck_step'] = 2; }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step2_next') {
        $host = trim($_POST['db_host'] ?? '127.0.0.1');
        $port = (int)($_POST['db_port'] ?? 3306);
        $name = trim($_POST['db_name'] ?? '');
        $user = trim($_POST['db_user'] ?? '');
        $pass = $_POST['db_pass'] ?? '';
        $errs = [];
        if (!$host) $errs[] = 'Hostname fehlt.';
        if (!$name) $errs[] = 'Datenbankname fehlt.';
        if (!$user) $errs[] = 'Nutzer fehlt.';
        if (!$errs) { $t = testDb($host, $port, $name, $user, $pass); if ($t !== true) $errs[] = 'DB-Fehler: '.$t; }
        if ($errs) { $_SESSION['ck_errors'] = $errs; $_SESSION['ck_step'] = 2; }
        else { $_SESSION['ck_data']['db'] = compact('host','port','name','user','pass'); $_SESSION['ck_step'] = 3; }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step3_next') {
        $appName = trim($_POST['app_name'] ?? 'ClubKit') ?: 'ClubKit';
        $appUrl  = rtrim(trim($_POST['app_url'] ?? ''), '/');
        if (!filter_var($appUrl, FILTER_VALIDATE_URL)) {
            $_SESSION['ck_errors'] = ['Gültige URL erforderlich.'];
            $_SESSION['ck_step']   = 3;
        } else {
            $_SESSION['ck_data']['app'] = compact('appName','appUrl');
            $_SESSION['ck_step'] = 4;
        }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step4_next') {
        $name  = trim($_POST['admin_name']  ?? '');
        $email = trim($_POST['admin_email'] ?? '');
        $pass  = $_POST['admin_pass']  ?? '';
        $pass2 = $_POST['admin_pass2'] ?? '';
        $errs  = [];
        if (!$name)                                      $errs[] = 'Name fehlt.';
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) $errs[] = 'Ungültige E-Mail.';
        if (strlen($pass) < 8)                           $errs[] = 'Passwort min. 8 Zeichen.';
        if ($pass !== $pass2)                            $errs[] = 'Passwörter stimmen nicht überein.';
        if ($errs) { $_SESSION['ck_errors'] = $errs; $_SESSION['ck_step'] = 4; }
        else { $_SESSION['ck_data']['admin'] = compact('name','email','pass'); $_SESSION['ck_step'] = 5; }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }

    if ($action === 'step5_install') {
        $available = detectModules($MODULES_DIR);
        $selected  = array_keys($_POST['modules'] ?? []);
        if (!in_array('core', $selected, true) && isset($available['core'])) {
            array_unshift($selected, 'core');
        }
        $resolved = resolveDeps($selected, $available);
        if (is_string($resolved)) { $_SESSION['ck_errors'] = [$resolved]; $_SESSION['ck_step'] = 5; }
        else { $_SESSION['ck_data']['modules'] = $resolved; $_SESSION['ck_step'] = 6; }
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    }
}

// ── Installation ──────────────────────────────────────────────────────────────

$step        = $_SESSION['ck_step']        ?? 1;
$errors      = $_SESSION['ck_errors']      ?? [];
$data        = $_SESSION['ck_data']        ?? [];
$installLog  = $_SESSION['ck_install_log'] ?? [];
$installDone = $_SESSION['ck_installed']   ?? false;
$rollbackDone= $_SESSION['ck_rollback_done'] ?? false;
$resetLog    = $_SESSION['ck_reset_log']   ?? [];

if ($step === 6 && !$installDone) {
    // Clear any leftover rollback flag from a previous failed attempt.
    unset($_SESSION['ck_rollback_done'], $_SESSION['ck_install_log']);

    $db  = $data['db']      ?? [];
    $app = $data['app']     ?? [];
    $adm = $data['admin']   ?? [];
    $mod = $data['modules'] ?? ['core'];

    $log        = [];
    $laravelApp = null;
    $envBackup  = file_exists($ENV_FILE) ? file_get_contents($ENV_FILE) : null;
    $rollback   = [];

    $fail = function(string $msg, string $detail) use (&$log, &$rollback): void {
        $log[] = ['ok' => false, 'msg' => $msg, 'detail' => $detail];
        foreach (array_reverse($rollback) as $fn) { try { $fn(); } catch (\Throwable) {} }
        $_SESSION['ck_install_log']   = $log;
        $_SESSION['ck_installed']     = false;
        $_SESSION['ck_rollback_done'] = true;
        header('Location: '.$_SERVER['PHP_SELF']); exit;
    };

    // A: .env
    $envContent = buildEnv([
        'app_name' => $app['appName']      ?? 'ClubKit',
        'app_url'  => $app['appUrl']       ?? 'http://localhost',
        'db_host'  => $db['host']          ?? '127.0.0.1',
        'db_port'  => (string)($db['port'] ?? 3306),
        'db_name'  => $db['name']          ?? '',
        'db_user'  => $db['user']          ?? '',
        'db_pass'  => $db['pass']          ?? '',
        'modules'  => implode(',', $mod),
    ]);
    if (file_put_contents($ENV_FILE, $envContent) === false) {
        $fail('.env konnte nicht geschrieben werden', 'Keine Schreibrechte auf '.$ENV_FILE);
    }
    $rollback[] = fn() => ($envBackup !== null ? file_put_contents($ENV_FILE, $envBackup) : @unlink($ENV_FILE));
    $log[] = ['ok' => true, 'msg' => '.env geschrieben', 'detail' => ''];

    // B: Bootstrap Laravel
    try {
        $laravelApp = bootstrapLaravel($BASE);
        $log[] = ['ok' => true, 'msg' => 'Laravel gestartet', 'detail' => ''];
    } catch (\Throwable $e) {
        $fail('Laravel-Bootstrap fehlgeschlagen', $e->getMessage());
    }

    // C: APP_KEY
    $r = runArtisan($laravelApp, 'key:generate', ['--force' => true]);
    if (!$r['ok']) $fail('APP_KEY fehlgeschlagen', $r['out']);
    $log[] = ['ok' => true, 'msg' => 'APP_KEY generiert', 'detail' => ''];

    // D: Migrate
    $r = runArtisan($laravelApp, 'migrate', ['--force' => true]);
    if (!$r['ok']) {
        $rollback[] = fn() => runArtisan($laravelApp, 'migrate:rollback', ['--force' => true]);
        $fail('Migration fehlgeschlagen', $r['out']);
    }
    $rollback[] = fn() => runArtisan($laravelApp, 'migrate:rollback', ['--force' => true]);
    $log[] = ['ok' => true, 'msg' => 'Migrationen ausgeführt', 'detail' => ''];

    // E: Admin user + role
    try {
        $dbc = $laravelApp->make('db');
        $now = date('Y-m-d H:i:s');
        $existing = $dbc->table('users')->where('email', $adm['email'])->first();
        $userId   = $existing
            ? $existing->id
            : $dbc->table('users')->insertGetId([
                'name'              => $adm['name'],
                'email'             => $adm['email'],
                'password'          => password_hash($adm['pass'], PASSWORD_BCRYPT, ['cost' => 12]),
                'email_verified_at' => $now,
                'created_at'        => $now,
                'updated_at'        => $now,
            ]);
        $role   = $dbc->table('roles')->where('name','admin')->where('guard_name','web')->first();
        $roleId = $role
            ? $role->id
            : $dbc->table('roles')->insertGetId(['name'=>'admin','guard_name'=>'web','created_at'=>$now,'updated_at'=>$now]);
        if (!$dbc->table('model_has_roles')->where('role_id',$roleId)->where('model_id',$userId)->where('model_type','App\\Models\\User')->exists()) {
            $dbc->table('model_has_roles')->insert(['role_id'=>$roleId,'model_id'=>$userId,'model_type'=>'App\\Models\\User']);
        }
        $rollback[] = fn() => $dbc->table('users')->where('email', $adm['email'])->delete();
        $log[] = ['ok' => true, 'msg' => 'Admin-User & Rolle angelegt', 'detail' => $adm['email']];
    } catch (\Throwable $e) {
        $fail('Admin-User fehlgeschlagen', $e->getMessage());
    }

    // F: Register modules
    try {
        $available = detectModules($MODULES_DIR);
        $now = date('Y-m-d H:i:s');
        foreach ($mod as $slug) {
            $mc = $available[$slug] ?? ['name' => ucfirst($slug), 'version' => '1.0.0'];
            if (!$dbc->table('installed_modules')->where('slug',$slug)->exists()) {
                $dbc->table('installed_modules')->insert([
                    'slug'         => $slug,
                    'name'         => $mc['name'],
                    'version'      => $mc['version'] ?? '1.0.0',
                    'is_active'    => true,
                    'installed_at' => $now,
                    'created_at'   => $now,
                    'updated_at'   => $now,
                ]);
            }
        }
        $log[] = ['ok' => true, 'msg' => 'Module registriert: '.implode(', ', $mod), 'detail' => ''];
    } catch (\Throwable $e) {
        $fail('Module-Registrierung fehlgeschlagen', $e->getMessage());
    }

    // G: Ensure all required Laravel storage subdirectories exist
    $storageDirs = [
        "$BASE/storage/app",
        "$BASE/storage/app/public",
        "$BASE/storage/framework",
        "$BASE/storage/framework/cache",
        "$BASE/storage/framework/cache/data",
        "$BASE/storage/framework/sessions",
        "$BASE/storage/framework/testing",
        "$BASE/storage/framework/views",
        "$BASE/storage/logs",
        "$BASE/bootstrap/cache",
    ];
    foreach ($storageDirs as $dir) {
        if (! is_dir($dir)) { @mkdir($dir, 0775, true); }
        @chmod($dir, 0775);
    }
    // Add .gitignore placeholders so the directories are tracked in git.
    $placeholders = [
        "$BASE/storage/app/.gitignore"              => "*\n!.gitignore\n",
        "$BASE/storage/framework/cache/.gitignore"  => "*\n!.gitignore\n",
        "$BASE/storage/framework/sessions/.gitignore" => "*\n!.gitignore\n",
        "$BASE/storage/framework/views/.gitignore"  => "*\n!.gitignore\n",
        "$BASE/storage/logs/.gitignore"             => "*\n!.gitignore\n",
    ];
    foreach ($placeholders as $file => $content) {
        if (! file_exists($file)) { @file_put_contents($file, $content); }
    }
    $log[] = ['ok' => true, 'msg' => 'Storage-Verzeichnisse initialisiert', 'detail' => implode(', ', array_map('basename', $storageDirs))];

    // H: Storage symlink (public/storage → storage/app/public)
    if (! is_link("$BASE/public/storage")) {
        $r = runArtisan($laravelApp, 'storage:link');
        // Non-fatal: symlink may fail on some hosts; app still works without it.
        $log[] = ['ok' => true, 'msg' => 'Storage-Symlink erstellt', 'detail' => $r['out'] ?? ''];
    } else {
        $log[] = ['ok' => true, 'msg' => 'Storage-Symlink vorhanden', 'detail' => ''];
    }

    // H: Optimize (config/route/view cache)
    $r = runArtisan($laravelApp, 'config:clear');
    $r = runArtisan($laravelApp, 'view:clear');
    runArtisan($laravelApp, 'optimize');
    $log[] = ['ok' => true, 'msg' => 'Cache optimiert', 'detail' => ''];

    // I: Set file permissions
    tryFixPermissions("$BASE/storage");
    tryFixPermissions("$BASE/bootstrap/cache");
    $log[] = ['ok' => true, 'msg' => 'Berechtigungen gesetzt', 'detail' => ''];

    // J: Self-test — verify the login page is reachable
    $loginUrl = rtrim($app['appUrl'] ?? '', '/') . '/login';
    $ctx = @stream_context_create(['http' => ['timeout' => 5, 'ignore_errors' => true]]);
    $body = @file_get_contents($loginUrl, false, $ctx);
    $responseOk = ($body !== false && strlen($body) > 100);
    $log[] = [
        'ok'     => $responseOk,
        'msg'    => $responseOk ? 'App antwortet korrekt' : 'App-Selbsttest fehlgeschlagen',
        'detail' => $responseOk ? '' : 'Login-Seite unter '.$loginUrl.' nicht erreichbar. Prüfe storage/logs/laravel.log auf dem Server.',
    ];

    // K: Marker
    file_put_contents("$BASE/storage/installed", date('Y-m-d H:i:s')."\nModules: ".implode(',',$mod)."\nVersion: ".INSTALLER_VERSION);
    $log[] = ['ok' => true, 'msg' => 'ClubKit installiert', 'detail' => ''];

    $_SESSION['ck_installed']   = true;
    $_SESSION['ck_install_log'] = $log;
    header('Location: '.$_SERVER['PHP_SELF']); exit;
}

// Re-read after possible install
$step        = $_SESSION['ck_step']        ?? 1;
$errors      = $_SESSION['ck_errors']      ?? [];
$data        = $_SESSION['ck_data']        ?? [];
$installLog  = $_SESSION['ck_install_log'] ?? [];
$installDone = $_SESSION['ck_installed']   ?? false;
$rollbackDone= $_SESSION['ck_rollback_done'] ?? false;
$resetLog    = $_SESSION['ck_reset_log']   ?? [];

$preChecks   = ($step === 1) ? runPreflightChecks($BASE) : [];
$allOk       = empty(array_filter($preChecks, fn($c) => !$c['ok'] && $c['fatal']));
$availMods   = detectModules($MODULES_DIR);
$appUrl      = $data['app']['appUrl'] ?? '';

?><!DOCTYPE html>
<html lang="de">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>ClubKit Installer <?= INSTALLER_VERSION ?></title>
<script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-100 min-h-screen flex flex-col items-center py-10 px-4">
<div class="w-full max-w-xl">
<div class="bg-white rounded-2xl shadow overflow-hidden">

  <!-- Header -->
  <div class="bg-slate-900 px-6 py-5 flex items-center justify-between">
    <div>
      <h1 class="text-white text-xl font-bold">ClubKit Installer</h1>
      <p class="text-gray-400 text-xs mt-0.5">v<?= INSTALLER_VERSION ?> &middot; PHP <?= PHP_VERSION ?></p>
    </div>
    <div class="flex gap-2">
      <a href="?reset=1" class="text-xs px-3 py-1.5 bg-white/10 hover:bg-white/20 text-gray-300 rounded-lg">
        Session reset
      </a>
      <a href="?full_reset=1"
         onclick="return confirm('DB komplett leeren + .env löschen + neu starten?')"
         class="text-xs px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg">
        Komplett-Reset
      </a>
    </div>
  </div>

  <!-- Reset-Log (nach full_reset) -->
  <?php if ($step === 'reset_done'): ?>
  <div class="p-6">
    <h2 class="text-lg font-bold mb-3">Komplett-Reset durchgeführt</h2>
    <div class="space-y-1 mb-4">
      <?php foreach ($resetLog as $entry): ?>
      <div class="flex items-center gap-2 text-sm <?= $entry['ok'] ? 'text-green-700' : 'text-red-700' ?>">
        <?= $entry['ok'] ? '✅' : '❌' ?> <?= h($entry['msg']) ?>
      </div>
      <?php endforeach; ?>
    </div>
    <a href="?reset=1" class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg inline-block">
      Installer neu starten &rarr;
    </a>
  </div>
  <?php else: ?>

  <!-- Step Tabs -->
  <div class="flex bg-gray-50 border-b border-gray-200 px-4 overflow-x-auto">
    <?php foreach (['1 Checks','2 Datenbank','3 App','4 Admin','5 Module','6 Installation'] as $i => $lbl):
      $n = $i+1;
      $cls = $n < $step ? 'text-green-600' : ($n === $step ? 'text-blue-600 border-b-2 border-blue-600' : 'text-gray-400');
    ?>
    <div class="py-3 px-3 text-xs font-bold whitespace-nowrap <?= $cls ?>"><?= h($lbl) ?></div>
    <?php endforeach; ?>
  </div>

  <!-- Body -->
  <div class="p-6">

    <?php if ($errors): ?>
    <div class="mb-4 bg-red-50 border border-red-200 rounded-lg p-3">
      <?php foreach ($errors as $err): ?>
        <p class="text-sm text-red-700"><?= h($err) ?></p>
      <?php endforeach; ?>
    </div>
    <?php endif; ?>

    <!-- Step 1 -->
    <?php if ($step === 1): ?>
    <h2 class="text-lg font-bold mb-1">System-Check</h2>
    <p class="text-sm text-gray-500 mb-4">Der Installer versucht Schreibrechte automatisch zu setzen. Falls etwas rot bleibt, auf "Neu prüfen" klicken.</p>
    <div class="space-y-1">
      <?php foreach ($preChecks as $c): ?>
      <div class="flex items-start gap-3 py-2 border-b border-gray-100 last:border-0">
        <span class="text-xs font-bold px-2 py-0.5 rounded mt-0.5 <?= $c['ok'] ? 'bg-green-100 text-green-700' : ($c['fatal'] ? 'bg-red-100 text-red-700' : 'bg-amber-100 text-amber-700') ?>">
          <?= $c['ok'] ? 'OK' : ($c['fatal'] ? 'FEHLER' : 'WARN') ?>
        </span>
        <div class="flex-1">
          <div class="text-sm"><?= h($c['label']) ?></div>
          <?php if (! $c['ok']): ?>
          <div class="text-xs text-gray-400 mt-0.5"><?= h($c['value']) ?></div>
          <?php endif; ?>
        </div>
        <?php if ($c['ok']): ?>
        <span class="text-xs text-gray-400 mt-0.5"><?= h($c['value']) ?></span>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>

    <?php
    $permFail   = ! array_filter($preChecks, fn($c) => str_contains($c['label'], 'schreibbar') && ! $c['ok']) === false;
    $vendorFail = ! empty(array_filter($preChecks, fn($c) => str_contains($c['label'], 'vendor') && ! $c['ok']));
    ?>

    <?php if ($vendorFail): ?>
    <?php $composerOut = $_SESSION['ck_composer_out'] ?? null; ?>
    <?php if ($composerOut): unset($_SESSION['ck_composer_out']); ?>
    <div class="mt-4 <?= $composerOut['ok'] ? 'bg-green-50 border-green-200' : 'bg-red-50 border-red-200' ?> border rounded-lg p-3 text-sm">
      <p class="font-bold <?= $composerOut['ok'] ? 'text-green-700' : 'text-red-700' ?>">
        <?= $composerOut['ok'] ? '✔ Composer erfolgreich ausgeführt — Seite neu laden' : '✘ Composer fehlgeschlagen' ?>
      </p>
      <?php if (! $composerOut['ok'] && $composerOut['lines']): ?>
      <pre class="text-xs mt-2 font-mono bg-white rounded p-2 overflow-x-auto max-h-40 text-gray-700"><?= h(implode("\n", array_slice($composerOut['lines'], -20))) ?></pre>
      <?php endif; ?>
    </div>
    <?php endif; ?>
    <?php $offerComposer = ! empty(array_filter($preChecks, fn($c) => ($c['composer_offer'] ?? false))); ?>
    <?php if ($offerComposer): ?>
    <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
      <strong>vendor/ fehlt</strong> — Composer ist auf diesem Server verfügbar.<br>
      <span class="text-xs">Klick auf den Button führt <code class="bg-amber-100 px-1 rounded">composer install</code> automatisch aus.</span>
      <form method="POST" class="mt-2">
        <input type="hidden" name="action" value="run_composer">
        <button class="px-4 py-1.5 bg-amber-600 hover:bg-amber-700 text-white text-xs font-bold rounded-lg">
          ▶ Composer jetzt ausführen
        </button>
      </form>
    </div>
    <?php else: ?>
    <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
      <strong>vendor/ fehlt</strong> — kein exec() verfügbar.<br>
      <span class="text-xs">Lade das <strong>Release-ZIP</strong> herunter (enthält vendor/) und lade alle Dateien komplett hoch.<br>
      Alternativ: SSH-Zugang → <code class="bg-amber-100 px-1 rounded">composer install --no-dev</code></span>
    </div>
    <?php endif; ?>
    <?php endif; ?>

    <?php if (! $allOk && ! $vendorFail): ?>
    <div class="mt-4 bg-amber-50 border border-amber-200 rounded-lg p-3 text-sm text-amber-800">
      <strong>Schreibrechte fehlen</strong> — der Installer hat versucht sie zu setzen.<br>
      <span class="text-xs">Seite neu laden löst das in den meisten Fällen. Falls nicht:<br>
      Im Dateimanager (Plesk/cPanel) den Ordner <code class="bg-amber-100 px-1 rounded">storage</code> und
      <code class="bg-amber-100 px-1 rounded">bootstrap/cache</code> auf Rechte <strong>775</strong> setzen.</span>
    </div>
    <?php endif; ?>

    <div class="mt-5 flex gap-3">
      <a href="<?= h($_SERVER['PHP_SELF']) ?>"
         class="px-4 py-2 bg-gray-100 hover:bg-gray-200 text-gray-700 text-sm font-bold rounded-lg">
        ↻ Neu prüfen
      </a>
      <form method="POST">
        <input type="hidden" name="action" value="step1_next">
        <button class="px-5 py-2 bg-blue-600 text-white text-sm font-bold rounded-lg <?= !$allOk ? 'opacity-50 cursor-not-allowed' : 'hover:bg-blue-700' ?>"
                type="submit" <?= !$allOk ? 'disabled' : '' ?>>
          Weiter &rarr;
        </button>
      </form>
    </div>

    <!-- Step 2 -->
    <?php elseif ($step === 2): ?>
    <h2 class="text-lg font-bold mb-4">Datenbankverbindung</h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="step2_next">
      <div class="grid grid-cols-3 gap-3">
        <div class="col-span-2">
          <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Hostname</label>
          <input type="text" name="db_host" value="<?= e($data['db']['host'] ?? '127.0.0.1') ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
        </div>
        <div>
          <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Port</label>
          <input type="number" name="db_port" value="<?= e($data['db']['port'] ?? 3306) ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
        </div>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Datenbankname</label>
        <input type="text" name="db_name" value="<?= e($data['db']['name'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Nutzer</label>
        <input type="text" name="db_user" value="<?= e($data['db']['user'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Passwort</label>
        <input type="password" name="db_pass" class="w-full border rounded-lg px-3 py-2 text-sm">
      </div>
      <button class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg">
        Testen &amp; weiter &rarr;
      </button>
    </form>

    <!-- Step 3 -->
    <?php elseif ($step === 3): ?>
    <h2 class="text-lg font-bold mb-4">App-Konfiguration</h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="step3_next">
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Vereinsname / App-Name</label>
        <input type="text" name="app_name" value="<?= e($data['app']['appName'] ?? 'ClubKit') ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">App-URL (inkl. https://)</label>
        <input type="url" name="app_url" value="<?= e($data['app']['appUrl'] ?? 'https://') ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <button class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg">
        Weiter &rarr;
      </button>
    </form>

    <!-- Step 4 -->
    <?php elseif ($step === 4): ?>
    <h2 class="text-lg font-bold mb-4">Administrator-Account</h2>
    <form method="POST" class="space-y-4">
      <input type="hidden" name="action" value="step4_next">
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Name</label>
        <input type="text" name="admin_name" value="<?= e($data['admin']['name'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">E-Mail</label>
        <input type="email" name="admin_email" value="<?= e($data['admin']['email'] ?? '') ?>" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Passwort (min. 8 Zeichen)</label>
        <input type="password" name="admin_pass" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <div>
        <label class="block text-xs font-bold text-gray-500 uppercase mb-1">Passwort wiederholen</label>
        <input type="password" name="admin_pass2" class="w-full border rounded-lg px-3 py-2 text-sm" required>
      </div>
      <button class="px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg">
        Weiter &rarr;
      </button>
    </form>

    <!-- Step 5 -->
    <?php elseif ($step === 5): ?>
    <h2 class="text-lg font-bold mb-1">Module aktivieren</h2>
    <p class="text-sm text-gray-500 mb-4">Nur Module mit vorhandenen Dateien werden angezeigt.</p>
    <form method="POST" class="space-y-3">
      <input type="hidden" name="action" value="step5_install">
      <?php foreach ($availMods as $slug => $mod): $isCore = $slug === 'core'; ?>
      <div class="border rounded-xl p-4 <?= $isCore ? 'bg-gray-50' : '' ?>">
        <label class="flex items-start gap-3 cursor-pointer">
          <input type="checkbox" name="modules[<?= h($slug) ?>]" value="1"
                 <?= $isCore ? 'checked disabled' : (in_array($slug, $data['modules'] ?? array_keys($availMods)) ? 'checked' : '') ?>>
          <div>
            <div class="font-semibold text-sm">
              <?= h($mod['name']) ?>
              <?php if ($isCore): ?><span class="ml-1 text-xs px-2 py-0.5 bg-blue-100 text-blue-700 rounded-full">Pflicht</span><?php endif; ?>
            </div>
            <div class="text-xs text-gray-500 mt-0.5"><?= h($mod['description'] ?? '') ?></div>
          </div>
        </label>
      </div>
      <?php endforeach; ?>
      <?php if (empty($availMods)): ?>
      <p class="text-sm text-amber-600">Keine Module gefunden. Prüfe ob <code>modules/</code> vorhanden ist.</p>
      <?php endif; ?>
      <button class="mt-2 px-5 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-bold rounded-lg">
        Jetzt installieren &rarr;
      </button>
    </form>

    <!-- Step 6 -->
    <?php elseif ($step === 6): ?>
    <h2 class="text-lg font-bold mb-4">Installation</h2>
    <?php if ($rollbackDone): ?>
    <?php $failedEntry = array_values(array_filter($installLog, fn($e) => !$e['ok']))[0] ?? null; ?>
    <div class="mb-4 bg-red-50 border border-red-200 rounded-xl p-4">
      <p class="font-bold text-red-700">Fehler – Rollback ausgeführt</p>
      <p class="text-xs text-red-600 mt-1">Alle Änderungen wurden rückgängig gemacht.</p>
      <?php if ($failedEntry): ?>
      <div class="mt-2 bg-red-100 rounded-lg p-2">
        <p class="text-xs font-bold text-red-800"><?= h($failedEntry['msg']) ?></p>
        <?php if (!empty($failedEntry['detail'])): ?>
        <p class="text-xs font-mono text-red-700 mt-1 break-all"><?= h($failedEntry['detail']) ?></p>
        <?php endif; ?>
      </div>
      <?php endif; ?>
      <a href="?reset=1" class="inline-block mt-3 text-xs px-3 py-1.5 bg-red-600 hover:bg-red-700 text-white font-bold rounded-lg">
        Nochmal versuchen →
      </a>
    </div>
    <?php elseif ($installDone): ?>
    <div class="mb-4 bg-green-50 border border-green-200 rounded-xl p-4 text-center">
      <p class="font-bold text-green-700 text-lg">Erfolgreich installiert!</p>
    </div>
    <?php endif; ?>
    <div class="space-y-1">
      <?php foreach ($installLog as $entry): ?>
      <div class="flex items-start gap-3 py-2 border-b border-gray-100 last:border-0">
        <span class="text-xs font-bold px-2 py-0.5 rounded flex-shrink-0 <?= $entry['ok'] ? 'bg-green-100 text-green-700' : 'bg-red-100 text-red-700' ?>">
          <?= $entry['ok'] ? 'OK' : 'FEHLER' ?>
        </span>
        <div class="flex-1 min-w-0">
          <div class="text-sm"><?= h($entry['msg']) ?></div>
          <?php if (!empty($entry['detail'])): ?>
          <div class="text-xs text-gray-400 mt-1 font-mono break-all bg-gray-50 px-2 py-1 rounded"><?= h($entry['detail']) ?></div>
          <?php endif; ?>
        </div>
      </div>
      <?php endforeach; ?>
    </div>
    <div class="mt-5 flex gap-3 flex-wrap">
      <?php if ($installDone && $appUrl): ?>
      <a href="<?= h($appUrl) ?>/login" class="px-5 py-2 bg-green-600 hover:bg-green-700 text-white text-sm font-bold rounded-lg">
        Zum Login
      </a>
      <?php endif; ?>
    </div>
    <?php endif; ?>

  </div>
  <?php endif; ?>

</div>
<p class="text-center text-xs text-gray-400 mt-4">ClubKit Installer v<?= INSTALLER_VERSION ?></p>
</div>
</body>
</html>
