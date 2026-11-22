<?php
/**
 * VIRA CRM — Host Compatibility Check (Phase 0, v2)
 * -------------------------------------------------
 * این اسکریپت را در ساب‌دامین crm.viraagency.co (مسیر محافظت‌شده با HTTP Basic Auth) قرار دهید
 * و یک بار اجرا کنید. سپس خروجی JSON را برای تیم مهندسی بفرستید.
 *
 * اجرا:
 *   php host-compatibility-check.php                (CLI — خروجی JSON)
 *   php host-compatibility-check.php --text         (CLI — خروجی خوانا)
 *   https://crm.viraagency.co/_diag/host-check.php?token=CHANGE_ME&format=json   (وب)
 *
 * قواعد امنیتی (رعایت‌شده در همین فایل):
 *   - فقط-خواندنی: هیچ فایل، دیتابیس یا تنظیماتی تغییر نمی‌کند. هیچ DDL/DML اجرا نمی‌شود.
 *   - هیچ رمز یا کلیدی در فایل نیست؛ اتصال DB فقط از متغیرهای محیطی خوانده می‌شود.
 *   - در حالت وب فقط با توکن صحیح اجرا می‌شود (hash_equals + الزام تغییر توکن پیش‌فرض).
 *   - پس از استفاده این فایل را حذف کنید.
 *
 * سازگاری نحوی: این فایل عمداً با نگارش PHP 5.6 نوشته شده است (بدون type hint، بدون
 * arrow function، بدون ??، بدون Throwable، بدون JSON_THROW_ON_ERROR). دلیل: اگر هاست
 * PHP قدیمی داشته باشد، باید بتوانیم همان نسخه را گزارش کنیم؛ اسکریپتی که روی PHP 7.2
 * خطای نحوی بدهد هیچ اطلاعاتی تولید نمی‌کند و کل هدف را از بین می‌برد.
 *
 * دامنه: این اسکریپت «شواهد» جمع می‌کند؛ «تأیید سازگاری» نیست. تأیید نهایی فقط پس از
 * بررسی خروجی واقعی روی هاست و سپس نصب واقعی Laravel انجام می‌شود.
 */

// ── حالت اجرا ──────────────────────────────────────────────────────────────────
// در وب، ممکن است $argv توسط register_argc_argv از query string پر شود؛ بنابراین
// برای دور زدن توکن هرگز به $argv به‌تنهایی اتکا نمی‌کنیم.
$cli = (PHP_SAPI === 'cli') || (getenv('VIRA_DIAG_FORCE_CLI') === '1');

$wantJson = false;
if ($cli) {
    $args = isset($argv) && is_array($argv) ? $argv : array();
    $wantJson = in_array('--json', $args, true) || !in_array('--text', $args, true);
    if (getenv('VIRA_DIAG_FORMAT') === 'text') {
        $wantJson = false;
    }
    if (getenv('VIRA_DIAG_FORMAT') === 'json') {
        $wantJson = true;
    }
}

if (!$cli) {
    $placeholder = 'CHANGE_ME_BEFORE_UPLOAD';
    $expected = getenv('VIRA_DIAG_TOKEN');
    if ($expected === false || $expected === '' || $expected === $placeholder) {
        $expected = $placeholder;
    }
    $given = isset($_GET['token']) ? (string) $_GET['token'] : '';
    if (!function_exists('hash_equals')) {
        http_response_code(500);
        header('Content-Type: text/plain; charset=utf-8');
        echo "This PHP build is too old (no hash_equals). Report the PHP version manually.\n";
        exit;
    }
    if (!hash_equals($expected, $given) || $expected === $placeholder) {
        http_response_code(403);
        header('Content-Type: text/plain; charset=utf-8');
        echo "403 Forbidden — set VIRA_DIAG_TOKEN, then call with ?token=<your-token>\n";
        exit;
    }
    header('Content-Type: text/plain; charset=utf-8');
    $wantJson = isset($_GET['format']) && $_GET['format'] === 'json';
}

// ── ابزارهای کمکی ──────────────────────────────────────────────────────────────
function vira_ext($name)
{
    return extension_loaded($name);
}

function vira_ini($key)
{
    $v = ini_get($key);
    return ($v === false || $v === '') ? '(empty)' : $v;
}

/** آیا تابع داده‌شده واقعاً قابل فراخوانی است (نه فقط موجود)؟ */
function vira_callable($fn)
{
    if (!function_exists($fn)) {
        return false;
    }
    $disabled = array_map('trim', explode(',', (string) ini_get('disable_functions')));
    return !in_array($fn, $disabled, true);
}

/**
 * اجرای یک فرمان فقط-خواندنی با مهلت مشخص. هیچ نوشتنی انجام نمی‌دهد.
 * خروجی: array('ok'=>bool,'out'=>string,'code'=>int|null,'reason'=>string)
 */
function vira_probe($cmd, $timeoutSeconds)
{
    if (!vira_callable('proc_open')) {
        return array('ok' => false, 'out' => '', 'code' => null,
                     'reason' => 'proc_open unavailable (disabled or not present)');
    }
    $desc = array(0 => array('pipe', 'r'), 1 => array('pipe', 'w'), 2 => array('pipe', 'w'));
    $pipes = array();
    $proc = @proc_open($cmd . ' 2>&1', $desc, $pipes);
    if (!is_resource($proc)) {
        return array('ok' => false, 'out' => '', 'code' => null, 'reason' => 'proc_open failed');
    }
    fclose($pipes[0]);
    stream_set_blocking($pipes[1], false);
    stream_set_blocking($pipes[2], false);
    $out = '';
    $deadline = microtime(true) + $timeoutSeconds;
    while (microtime(true) < $deadline) {
        $chunk = stream_get_contents($pipes[1]);
        if ($chunk !== false && $chunk !== '') {
            $out .= $chunk;
        }
        $st = proc_get_status($proc);
        if (!$st['running']) {
            break;
        }
        usleep(50000);
    }
    $err = stream_get_contents($pipes[2]);
    if ($err) {
        $out .= $err;
    }
    $st = proc_get_status($proc);
    if ($st['running']) {
        @proc_terminate($proc, 9);
        fclose($pipes[1]);
        fclose($pipes[2]);
        @proc_close($proc);
        return array('ok' => false, 'out' => trim($out), 'code' => null, 'reason' => 'timeout');
    }
    $code = $st['exitcode'];
    fclose($pipes[1]);
    fclose($pipes[2]);
    @proc_close($proc);
    return array('ok' => ($code === 0), 'out' => trim($out), 'code' => $code, 'reason' => '');
}

function vira_first_line($s, $maxLen)
{
    $s = trim((string) $s);
    if ($s === '') {
        return '';
    }
    $lines = preg_split('/\r\n|\r|\n/', $s);
    $line = trim($lines[0]);
    if (strlen($line) > $maxLen) {
        $line = substr($line, 0, $maxLen) . '…';
    }
    return $line;
}

// ── PHP ───────────────────────────────────────────────────────────────────────
$c = array();

$c['php.version'] = PHP_VERSION;
$c['php.version_id'] = PHP_VERSION_ID;
$c['php.sapi'] = PHP_SAPI;
$c['php.os'] = PHP_OS . ' / ' . php_uname('r');
$c['php.arch'] = php_uname('m');
$c['php.supports_laravel12'] = (PHP_VERSION_ID >= 80200);
$c['php.supports_laravel13'] = (PHP_VERSION_ID >= 80300);
$c['php.satisfies_proposed_packages'] = (PHP_VERSION_ID >= 80200);
$c['php.memory_limit'] = vira_ini('memory_limit');
$c['php.max_execution_time'] = vira_ini('max_execution_time');
$c['php.upload_max_filesize'] = vira_ini('upload_max_filesize');
$c['php.post_max_size'] = vira_ini('post_max_size');
$c['php.max_input_vars'] = vira_ini('max_input_vars');
$c['php.max_file_uploads'] = vira_ini('max_file_uploads');
$c['php.timezone'] = date_default_timezone_get();
$c['php.date_timezone_ini'] = vira_ini('date.timezone');

// اجرای هسته‌ای که Laravel/بسته‌ها لازم دارند
$c['php.openssl_aes_256_gcm'] = in_array('aes-256-gcm', array_map('strtolower', openssl_get_cipher_methods()), true);
$c['php.openssl_aes_256_cbc'] = in_array('aes-256-cbc', array_map('strtolower', openssl_get_cipher_methods()), true);
$c['php.intl_available'] = vira_ext('intl');
$c['php.bcmath_available'] = vira_ext('bcmath');
$c['php.mysqli_available'] = vira_ext('mysqli');
$c['php.sodium_available'] = vira_ext('sodium');
$c['php.disable_functions'] = vira_ini('disable_functions');
$c['php.proc_open_callable'] = vira_callable('proc_open');
$c['php.exec_callable'] = vira_callable('exec');
$c['php.shell_exec_callable'] = vira_callable('shell_exec');
$c['php.passthru_callable'] = vira_callable('passthru');
$c['php.putenv_callable'] = vira_callable('putenv');

// ── افزونه‌ها ─────────────────────────────────────────────────────────────────
$c['ext.ctype'] = vira_ext('ctype');
$c['ext.curl'] = vira_ext('curl');
$c['ext.dom'] = vira_ext('dom');
$c['ext.fileinfo'] = vira_ext('fileinfo');
$c['ext.filter'] = vira_ext('filter');
$c['ext.gd'] = vira_ext('gd');
$c['ext.hash'] = vira_ext('hash');
$c['ext.iconv'] = vira_ext('iconv');
$c['ext.json'] = vira_ext('json');
$c['ext.libxml'] = vira_ext('libxml');
$c['ext.mbstring'] = vira_ext('mbstring');
$c['ext.openssl'] = vira_ext('openssl');
$c['ext.pdo'] = vira_ext('pdo');
$c['ext.pdo_mysql'] = vira_ext('pdo_mysql');
$c['ext.session'] = vira_ext('session');
$c['ext.simplexml'] = vira_ext('simplexml');
$c['ext.tokenizer'] = vira_ext('tokenizer');
$c['ext.xml'] = vira_ext('xml');
$c['ext.xmlreader'] = vira_ext('xmlreader');
$c['ext.xmlwriter'] = vira_ext('xmlwriter');
$c['ext.zip'] = vira_ext('zip');
$c['ext.zlib'] = vira_ext('zlib');
$c['ext.gmp_optional'] = vira_ext('gmp');
$c['ext.bz2_optional'] = vira_ext('bz2');
$c['ext.ldap_optional'] = vira_ext('ldap');

// ── فایل‌سیستم و مسیرها ───────────────────────────────────────────────────────
$docRoot = isset($_SERVER['DOCUMENT_ROOT']) ? $_SERVER['DOCUMENT_ROOT'] : '';
$c['fs.document_root'] = $docRoot !== '' ? $docRoot : '(unknown)';
$c['fs.script_dir'] = dirname(__FILE__);
$c['fs.script_dir_writable'] = is_writable(dirname(__FILE__));
$c['fs.parent_writable'] = is_writable(dirname(dirname(__FILE__)));
$c['fs.temp_dir'] = sys_get_temp_dir();
$c['fs.temp_writable'] = is_writable(sys_get_temp_dir());
$c['fs.disk_free_mb'] = (function_exists('disk_free_space') && @disk_free_space(dirname(__FILE__)) !== false)
    ? round(@disk_free_space(dirname(__FILE__)) / 1048576, 1) : '(unknown)';
$c['fs.upload_tmp_dir'] = vira_ini('upload_tmp_dir');
$c['fs.open_basedir'] = vira_ini('open_basedir');
$c['fs.docroot_looks_public'] = ($docRoot !== '' && is_dir($docRoot));

// ── CLI / Composer / نصب‌پذیری Laravel ───────────────────────────────────────
// این بخش فقط-خواندنی است: فقط «نسخه» را می‌پرسد و هیچ بسته‌ای نصب نمی‌کند.
$c['cli.php_binary'] = PHP_BINARY;
$c['cli.ini_loaded'] = php_ini_loaded_file() !== false ? php_ini_loaded_file() : '(none)';
$c['cli.ini_scanned_dir'] = php_ini_scanned_files() !== false ? php_ini_scanned_files() : '(none)';

$phpProbe = vira_probe(escapeshellarg(PHP_BINARY) . ' -r ' . escapeshellarg('echo PHP_VERSION;'), 5);
$c['cli.php_binary_runs'] = $phpProbe['ok'];
$c['cli.php_binary_reports'] = $phpProbe['ok'] ? vira_first_line($phpProbe['out'], 60)
    : ('n/a (' . ($phpProbe['reason'] !== '' ? $phpProbe['reason'] : 'exit ' . $phpProbe['code']) . ')');

$composerProbe = vira_probe('composer --version', 8);
if ($composerProbe['ok']) {
    $c['cli.composer_available'] = true;
    $c['cli.composer_version'] = vira_first_line($composerProbe['out'], 80);
} else {
    // composer may be installed under another name
    $composer2 = vira_probe('composer2 --version', 8);
    if ($composer2['ok']) {
        $c['cli.composer_available'] = true;
        $c['cli.composer_version'] = 'composer2: ' . vira_first_line($composer2['out'], 70);
    } else {
        $c['cli.composer_available'] = false;
        $c['cli.composer_version'] = 'not found (' . vira_first_line($composerProbe['out'] . ' ' . $composerProbe['reason'], 70) . ')';
    }
}
$c['cli.will_need_composer_per_host_policy'] = 'Composer is required to install Laravel; if absent, ask host to enable it or install locally and upload vendor/ + composer.lock';

// ── Cron / زمان‌بند ───────────────────────────────────────────────────────────
// بدون cron، «یادآور D-2» و «یادآور گزارش سئو» قابل اجرا نیستند. این بخش فقط
// وجود و خواندنی‌بودن را بررسی می‌کند؛ هیچ crontab‌ای نوشته نمی‌شود.
$c['cron.cli_sapi_available'] = (PHP_SAPI === 'cli' || vira_callable('proc_open'));
$etcCrontab = '/etc/crontab';
$c['cron.etc_crontab_exists'] = @is_file($etcCrontab);
$c['cron.etc_crontab_readable'] = @is_readable($etcCrontab);
$c['cron.etc_cron_d_readable'] = @is_dir('/etc/cron.d') && @is_readable('/etc/cron.d');
$c['cron.var_spool_cron_readable'] = @is_dir('/var/spool/cron') && @is_readable('/var/spool/cron');

$crondProbe = vira_probe('which crond cron crontab 2>/dev/null | head -3', 5);
$c['cron.binaries_found'] = $crondProbe['ok'] ? vira_first_line($crondProbe['out'], 120) : '(probe failed)';

$crontabList = vira_probe('crontab -l', 5);
if ($crontabList['code'] === 0) {
    // فقط تعداد خطوط را گزارش می‌کنیم، نه محتوای وظایف (حریم خصوصی)
    $lineCount = count(array_filter(preg_split('/\r\n|\r|\n/', $crontabList['out'])));
    $c['cron.user_crontab_readable'] = true;
    $c['cron.user_crontab_line_count'] = $lineCount;
} else {
    $c['cron.user_crontab_readable'] = false;
    $c['cron.user_crontab_line_count'] = 'n/a (' . vira_first_line($crontabList['out'] . ' ' . $crontabList['reason'], 60) . ')';
}

$systemctl = vira_probe('systemctl is-active cron 2>/dev/null || systemctl is-active crond 2>/dev/null', 5);
$c['cron.systemd_service_state'] = $systemctl['ok'] ? vira_first_line($systemctl['out'], 40) : '(not systemd / unknown)';

$c['cron.scheduler_feasible'] = ($c['cron.user_crontab_readable'] || $c['cron.etc_cron_d_readable'] || $c['cron.systemd_service_state'] !== '(not systemd / unknown)');
$c['cron.note'] = 'Required for D-2 invoice reminders and SEO report reminders. If cron is unavailable, the owner must choose an alternative scheduling option (Open Decision).';

// ── دیتابیس ──────────────────────────────────────────────────────────────────
$c['db.pdo_mysql_loaded'] = vira_ext('pdo_mysql');
$c['db.mysqli_loaded'] = vira_ext('mysqli');
$c['db.mysql_client_version'] = (vira_ext('mysqli') && function_exists('mysqli_get_client_info'))
    ? mysqli_get_client_info() : '(mysqli not loaded)';

$dbHost = getenv('VIRA_DB_HOST');
$dbName = getenv('VIRA_DB_NAME');
$dbUser = getenv('VIRA_DB_USER');
$dbPass = getenv('VIRA_DB_PASS');

if ($dbHost && $dbName && $dbUser !== false && vira_ext('pdo_mysql')) {
    try {
        $dsn = 'mysql:host=' . $dbHost . ';dbname=' . $dbName . ';charset=utf8mb4';
        $pdo = new PDO($dsn, $dbUser, $dbPass === false ? '' : $dbPass, array(
            PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
            PDO::ATTR_TIMEOUT => 5,
        ));
        $c['db.connection'] = 'ok';
        $c['db.server_version'] = (string) $pdo->query('SELECT VERSION()')->fetchColumn();
        $row = $pdo->query("SHOW VARIABLES LIKE 'character_set_server'")->fetch(PDO::FETCH_NUM);
        $c['db.charset_server'] = $row ? $row[1] : '(unknown)';
        $row = $pdo->query("SHOW VARIABLES LIKE 'collation_server'")->fetch(PDO::FETCH_NUM);
        $c['db.collation_server'] = $row ? $row[1] : '(unknown)';
        $c['db.sql_mode'] = (string) $pdo->query('SELECT @@sql_mode')->fetchColumn();
        $c['db.time_zone'] = (string) $pdo->query('SELECT @@time_zone')->fetchColumn();
        $c['db.default_storage_engine'] = (string) $pdo->query('SELECT @@default_storage_engine')->fetchColumn();
        $c['db.innodb_support'] = (stripos($c['db.default_storage_engine'], 'innodb') !== false);
        $c['db.max_allowed_packet_mb'] = round(((int) $pdo->query('SELECT @@max_allowed_packet')->fetchColumn()) / 1048576, 1);
        $c['db.lower_case_table_names'] = (string) $pdo->query('SELECT @@lower_case_table_names')->fetchColumn();
    } catch (Exception $e) {
        // پیام خطا ممکن است شامل نام کاربر باشد؛ فقط نوع خطا را گزارش می‌کنیم
        $c['db.connection'] = 'FAILED (' . get_class($e) . ')';
    }
} else {
    $c['db.connection'] = 'skipped (set VIRA_DB_HOST/VIRA_DB_NAME/VIRA_DB_USER/VIRA_DB_PASS to test)';
}

// ── وب‌سرور و امنیت ──────────────────────────────────────────────────────────
$https = (!empty($_SERVER['HTTPS']) && strtolower((string) $_SERVER['HTTPS']) !== 'off')
    || (isset($_SERVER['HTTP_X_FORWARDED_PROTO']) && strtolower((string) $_SERVER['HTTP_X_FORWARDED_PROTO']) === 'https')
    || (isset($_SERVER['SERVER_PORT']) && (int) $_SERVER['SERVER_PORT'] === 443);
$c['web.https_detected_this_request'] = $https;
$c['web.server_software'] = isset($_SERVER['SERVER_SOFTWARE']) ? $_SERVER['SERVER_SOFTWARE'] : '(unknown)';
$c['web.server_name'] = isset($_SERVER['SERVER_NAME']) ? $_SERVER['SERVER_NAME'] : '(unknown)';
$c['web.running_user'] = function_exists('posix_getpwuid') && function_exists('posix_geteuid')
    ? (($u = posix_getpwuid(posix_geteuid())) ? $u['name'] : '(unknown)') : '(unknown)';

$c['sec.display_errors'] = vira_ini('display_errors');
$c['sec.display_startup_errors'] = vira_ini('display_startup_errors');
$c['sec.expose_php'] = vira_ini('expose_php');
$c['sec.allow_url_fopen'] = vira_ini('allow_url_fopen');
$c['sec.allow_url_include'] = vira_ini('allow_url_include');
$c['sec.session_save_path'] = vira_ini('session.save_path');
$c['sec.session_save_path_writable'] = (@ini_get('session.save_path') !== '' && @is_writable((string) ini_get('session.save_path')));
$c['sec.session_cookie_httponly'] = vira_ini('session.cookie_httponly');
$c['sec.session_cookie_secure'] = vira_ini('session.cookie_secure');
$c['sec.session_cookie_samesite'] = vira_ini('session.cookie_samesite');
$c['sec.file_uploads'] = vira_ini('file_uploads');
$c['sec.note'] = 'Cookie/name prefix must be VIRA-specific so the CRM never shares a session with the WordPress site.';

// سازگاری JSON: تضمین می‌کنیم خروجی قابل‌پردازش است
$jsonOk = function_exists('json_encode');

// ── جمع‌بندی ─────────────────────────────────────────────────────────────────
$mustExt = array('ctype', 'curl', 'dom', 'fileinfo', 'filter', 'gd', 'hash', 'iconv', 'json',
                 'libxml', 'mbstring', 'openssl', 'pdo', 'pdo_mysql', 'session', 'simplexml',
                 'tokenizer', 'xml', 'xmlreader', 'xmlwriter', 'zip');
$missing = array();
foreach ($mustExt as $e) {
    if (!extension_loaded($e)) {
        $missing[] = $e;
    }
}

$criteria = array(
    'php_8_2_or_newer'          => (PHP_VERSION_ID >= 80200),
    'required_extensions'       => ($missing === array()),
    'openssl_aes_256_gcm'       => (bool) $c['php.openssl_aes_256_gcm'],
    'pdo_mysql'                 => (bool) vira_ext('pdo_mysql'),
    'json_encode_available'     => $jsonOk,
    'temp_dir_writable'         => (bool) $c['fs.temp_writable'],
    'composer_available'        => (bool) $c['cli.composer_available'],
    'cron_plausible'            => (bool) $c['cron.scheduler_feasible'],
    'https_on_this_request'     => (bool) $https,
    'display_errors_off'        => !in_array(strtolower((string) ini_get('display_errors')), array('1', 'on', 'true'), true),
);

$blocking = array();
$warnings = array();
foreach ($criteria as $name => $passed) {
    if ($passed) {
        continue;
    }
    $blockingNames = array('php_8_2_or_newer', 'required_extensions', 'openssl_aes_256_gcm',
                           'pdo_mysql', 'json_encode_available', 'temp_dir_writable');
    if (in_array($name, $blockingNames, true)) {
        $blocking[] = $name;
    } else {
        $warnings[] = $name;
    }
}

$verdict = 'HOST_COMPATIBILITY_PENDING_REVIEW';
if ($blocking !== array()) {
    $verdict = 'NOT_COMPATIBLE_FOR_LARAVEL_STACK';
} elseif ($warnings === array()) {
    $verdict = 'LIKELY_COMPATIBLE_PENDING_REAL_INSTALL';
} else {
    $verdict = 'LIKELY_COMPATIBLE_WITH_WARNINGS';
}

$report = array(
    'report_schema_version' => '2.0',
    'generated_at' => gmdate('c'),
    'generated_at_utc_note' => 'UTC; Tehran = UTC+03:30 (no DST since 2022)',
    'hostname' => function_exists('gethostname') ? gethostname() : '(unknown)',
    'execution_mode' => $cli ? 'cli' : 'web',
    'verdict' => $verdict,
    'verdict_meaning' => 'This is a screening result from a single request. Host compatibility is NOT confirmed until Laravel is actually installed and the app boots. See docs/phase-1/01a-host-compliance-report.md.',
    'criteria' => $criteria,
    'blocking_issues' => $blocking,
    'warnings' => $warnings,
    'missing_required_extensions' => $missing,
    'checks' => $c,
    'next_steps' => array(
        '1. Send this JSON to the engineering team unchanged.',
        '2. Delete this script from the host.',
        '3. Do not create the database or install Laravel until the owner approves Phase 1.',
    ),
);

// اعتبارسنجی JSON پیش از چاپ (اگر شکست بخورد، خروجی متنی برای عیب‌یابی می‌دهیم)
$json = json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
$jsonValid = ($json !== false) && (json_decode($json, true) !== null);

if ($wantJson) {
    if ($jsonValid) {
        echo $json, PHP_EOL;
    } else {
        echo "{\"verdict\":\"REPORT_ENCODING_FAILED\",\"json_error\":\"" . addslashes(json_last_error_msg()) . "\"}\n";
    }
    exit;
}

// خروجی خوانا
echo "VIRA CRM — Host Compatibility Report (screening)\n";
echo str_repeat('=', 78), "\n";
echo 'Generated (UTC): ' . $report['generated_at'] . "\n";
echo 'Host: ' . $report['hostname'] . ' | Mode: ' . $report['execution_mode'] . "\n";
echo 'Verdict: ' . $report['verdict'] . "\n";
echo 'Blocking: ' . ($blocking ? implode(', ', $blocking) : 'none') . "\n";
echo 'Warnings: ' . ($warnings ? implode(', ', $warnings) : 'none') . "\n";
echo 'Missing extensions: ' . ($missing ? implode(', ', $missing) : 'none') . "\n";
echo 'JSON encoding: ' . ($jsonValid ? 'valid' : 'FAILED') . "\n";
echo str_repeat('-', 78), "\n";
foreach ($c as $k => $v) {
    if (is_bool($v)) {
        $v = $v ? 'yes' : 'no';
    } elseif (is_array($v)) {
        $v = implode(', ', $v);
    }
    printf("%-34s %s\n", $k, (string) $v);
}
echo str_repeat('-', 78), "\n";
echo "NOTE: preliminary screening only. Real compatibility requires an actual Laravel install.\n";
echo "Send this output to the team, then DELETE this file from the host.\n";
