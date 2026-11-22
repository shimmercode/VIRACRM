<?php
/**
 * VIRA CRM — Host Compatibility Check Script (Phase 0)
 * ----------------------------------------------------
 * این اسکریپت را در ساب‌دامین crm.viraagency.co (روت موقت یا مسیر محافظت‌شده با HTTP Basic Auth)
 * قرار دهید و از طریق مرورگر یا CLI اجرا کنید. سپس خروجی JSON/متن را برای تیم مهندسی ارسال کنید.
 *
 * اجرا:
 *   php host-compatibility-check.php            (CLI — خروجی JSON)
 *   php host-compatibility-check.php --text     (CLI — خروجی خوانا)
 *   https://crm.viraagency.co/_diag/host-check.php?token=CHANGE_ME  (وب — نیازمند توکن)
 *
 * نکات امنیتی:
 *   - پس از استفاده، این فایل را حذف کنید.
 *   - در حالت وب، توکن را عوض کنید و پس از دریافت گزارش فایل را حذف یا با .htaccess محافظت کنید.
 *   - هیچ رمز یا کلید دیتابیسی را در این فایل قرار ندهید؛ اتصال DB فقط با متغیرهای محیطی قابل بررسی است.
 */

declare(strict_types=1);

// تشخیص CLI: در CLI واقعی یا با تنظیم صریح VIRA_DIAG_FORCE_CLI=1
// (در وب، $argv ممکن است توسط register_argc_argv از query string پر شود؛ بنابراین
//  هرگز به $argv به‌تنهایی برای دور زدن توکن اتکا نمی‌کنیم.)
$cli  = PHP_SAPI === 'cli' || getenv('VIRA_DIAG_FORCE_CLI') === '1';
$text = $cli && (in_array('--text', $argv ?? [], true) || getenv('VIRA_DIAG_FORMAT') === 'text');

if (!$cli) {
    // در حالت وب: دسترسی فقط با توکن صحیح
    $expected = getenv('VIRA_DIAG_TOKEN') ?: 'CHANGE_ME_BEFORE_UPLOAD';
    $given = $_GET['token'] ?? '';
    if (!hash_equals($expected, (string) $given) || $expected === 'CHANGE_ME_BEFORE_UPLOAD') {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden — set VIRA_DIAG_TOKEN or supply correct ?token=\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
}

$ext = static fn (string $e): bool => extension_loaded($e);

$checks = [
    // ── PHP ────────────────────────────────────────────────────────────────
    'php.version'                 => PHP_VERSION,
    'php.version_id'              => PHP_VERSION_ID,
    'php.sapi'                    => PHP_SAPI,
    'php.os'                      => PHP_OS_FAMILY . ' ' . php_uname('r'),
    'php.supports_laravel12'      => PHP_VERSION_ID >= 80200,
    'php.supports_laravel13'      => PHP_VERSION_ID >= 80300,
    'php.memory_limit'            => ini_get('memory_limit'),
    'php.max_execution_time'      => ini_get('max_execution_time'),
    'php.upload_max_filesize'     => ini_get('upload_max_filesize'),
    'php.post_max_size'           => ini_get('post_max_size'),
    'php.max_input_vars'          => ini_get('max_input_vars'),
    'php.timezone'                => date_default_timezone_get(),
    'php.intl'                    => $ext('intl'),
    'php.openssl_ciphers_aesgcm'  => in_array('aes-256-gcm', array_map('strtolower', openssl_get_cipher_methods()), true),

    // ── Extensions required by Laravel 12 + planned packages ───────────────
    'ext.ctype'                   => $ext('ctype'),
    'ext.curl'                    => $ext('curl'),
    'ext.dom'                     => $ext('dom'),
    'ext.fileinfo'                => $ext('fileinfo'),
    'ext.filter'                  => $ext('filter'),
    'ext.gd'                      => $ext('gd'),
    'ext.hash'                    => $ext('hash'),
    'ext.iconv'                   => $ext('iconv'),
    'ext.json'                    => $ext('json'),
    'ext.libxml'                  => $ext('libxml'),
    'ext.mbstring'                => $ext('mbstring'),
    'ext.openssl'                 => $ext('openssl'),
    'ext.pdo'                     => $ext('pdo'),
    'ext.pdo_mysql'               => $ext('pdo_mysql'),
    'ext.session'                 => $ext('session'),
    'ext.simplexml'               => $ext('simplexml'),
    'ext.tokenizer'               => $ext('tokenizer'),
    'ext.xml'                     => $ext('xml'),
    'ext.xmlreader'               => $ext('xmlreader'),
    'ext.xmlwriter'               => $ext('xmlwriter'),
    'ext.zip'                     => $ext('zip'),
    'ext.zlib'                    => $ext('zlib'),
    'ext.bcmath'                  => $ext('bcmath'),
    'ext.intl_recommended'        => $ext('intl'),

    // ── Filesystem / deployment ────────────────────────────────────────────
    'fs.document_root'            => $_SERVER['DOCUMENT_ROOT'] ?? '(unknown)',
    'fs.script_dir'               => __DIR__,
    'fs.writable_script_dir'      => is_writable(__DIR__),

    // ── CLI capabilities ───────────────────────────────────────────────────
    'cli.can_run_artisan'         => $cli && function_exists('proc_open'),
    'cli.proc_open_available'     => function_exists('proc_open'),
    'cli.composer_available'      => '(probing disabled in this build — run `composer --version` in SSH manually)',
    'cli.php_binary'              => PHP_BINARY,
    'cli.ini_loaded'              => php_ini_loaded_file() ?: '(none)',

    // ── MySQL client info (server version is only testable with credentials) ─
    'db.mysql_client_version'     => extension_loaded('mysqli') && function_exists('mysqli_get_client_info')
        ? mysqli_get_client_info() : '(mysqli not loaded)',
];

// ── Optional database connectivity check (env-only credentials) ────────────
$db = [
    'host' => getenv('VIRA_DB_HOST') ?: null,
    'name' => getenv('VIRA_DB_NAME') ?: null,
    'user' => getenv('VIRA_DB_USER') ?: null,
    'pass' => getenv('VIRA_DB_PASS') ?: null,
];
if ($db['host'] && $db['name'] && $db['user'] !== null && $ext('pdo_mysql')) {
    try {
        $pdo = new PDO(
            "mysql:host={$db['host']};dbname={$db['name']};charset=utf8mb4",
            $db['user'],
            $db['pass'] ?? '',
            [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION, PDO::ATTR_TIMEOUT => 5]
        );
        $checks['db.server_version']   = $pdo->query('SELECT VERSION()')->fetchColumn();
        $checks['db.connection']       = 'ok';
        $checks['db.charset']          = $pdo->query("SHOW VARIABLES LIKE 'character_set_server'")->fetchColumn(1);
        $checks['db.collation']        = $pdo->query("SHOW VARIABLES LIKE 'collation_server'")->fetchColumn(1);
        $checks['db.sql_mode']         = $pdo->query('SELECT @@sql_mode')->fetchColumn();
        $checks['db.time_zone']        = $pdo->query('SELECT @@time_zone')->fetchColumn();
        $checks['db.default_storage']  = $pdo->query('SELECT @@default_storage_engine')->fetchColumn();
    } catch (Throwable $e) {
        $checks['db.connection'] = 'FAILED: ' . $e->getMessage();
    }
} else {
    $checks['db.connection'] = 'skipped (set VIRA_DB_HOST/VIRA_DB_NAME/VIRA_DB_USER/VIRA_DB_PASS env vars to test)';
}

// ── Summary ────────────────────────────────────────────────────────────────
$mustExt = ['ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'iconv', 'json',
            'libxml', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'simplexml',
            'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip'];
$missing = array_values(array_filter($mustExt, static fn (string $e): bool => !extension_loaded($e)));
$ok = PHP_VERSION_ID >= 80200 && $missing === [];

$report = [
    'generated_at'   => date('c'),
    'hostname'       => gethostname(),
    'verdict'        => $ok ? 'LARAVEL_12_COMPATIBLE' : 'REQUIRES_REVIEW',
    'missing_required_extensions' => $missing,
    'checks'         => $checks,
];

if ($cli && !$text) {
    echo json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), PHP_EOL;
} else {
    echo "VIRA CRM — Host Compatibility Report\n";
    echo "Generated: {$report['generated_at']}\n";
    echo "Host: {$report['hostname']}\n";
    echo "Verdict: {$report['verdict']}\n";
    echo "Missing required extensions: " . ($missing ? implode(', ', $missing) : 'none') . "\n";
    echo str_repeat('-', 70), "\n";
    foreach ($checks as $key => $value) {
        $value = is_bool($value) ? ($value ? 'yes' : 'no') : (string) $value;
        printf("%-30s %s\n", $key, $value);
    }
}
