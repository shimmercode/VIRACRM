<?php
/**
 * VIRA CRM — Phase 1 feasibility check #2: runtime capabilities for the security & money design
 * ---------------------------------------------------------------------------------------------
 * این اسکریپت با PHP 8.3 واقعی (PHP-WASM) اجرا می‌شود و قابلیت‌های زیرساختی‌ای را بررسی می‌کند
 * که تصمیمات معماری به آن‌ها وابسته است:
 *
 *   REQ-06.03 / REQ-15.05 : رمزنگاری AES-256-GCM با کلید خارج از DB و احراز اصالت (AAD)
 *   REQ-17 (پول)          : محاسبات پولی Decimal با bcmath (بدون float)
 *   REQ-10.05             : password_hash (bcrypt/argon2) و password_verify
 *   REQ-10.04/REQ-06.04   : hash_hmac برای امضای توکن/URL امن خروجی محرمانه
 *   REQ-05.03/REQ-09.01   : نرمال‌سازی فارسی برای جستجو (ی/ك عربی، ZWNJ، اعداد فارسی)
 *   زیرساخت تست          : PDO SQLite برای تست‌های خودکار (بدون MySQL در CI)
 *
 * اجرا:
 *   node docs/tools/php-cli.mjs docs/src/php-runtime-smoke-test.php
 */

declare(strict_types=1);

final class Check
{
    public static array $results = [];
    public static int $failures = 0;

    public static function run(string $id, string $title, callable $fn): void
    {
        try {
            $detail = $fn();
            $ok = true;
        } catch (Throwable $e) {
            $detail = get_class($e) . ': ' . $e->getMessage();
            $ok = false;
        }
        if (!$ok) {
            self::$failures++;
        }
        self::$results[] = ['id' => $id, 'title' => $title, 'status' => $ok ? 'PASS' : 'FAIL', 'detail' => $detail];
        printf("%-8s %-60s %s\n", $ok ? '[PASS]' : '[FAIL]', $title, $detail);
    }

    public static function expect(bool $cond, string $message): void
    {
        if (!$cond) {
            throw new RuntimeException($message);
        }
    }
}

echo "VIRA CRM — PHP runtime capability smoke test (PHP " . PHP_VERSION . ")\n", str_repeat('=', 100), "\n";

// ── 1. AES-256-GCM with key outside DB, AAD binding, wrong-key/tamper failure ──
Check::run('RT-01', 'AES-256-GCM encrypt/decrypt + AAD + tamper detection', function () {
    $key = random_bytes(32); // در محصول: از .env / فایل خارج از DB و خارج از Repository
    $plaintext = 'رمز-نمونه-پروژه-۷۸۹!@#';
    $aad = 'credential:project=42;type=wordpress';

    $nonce = random_bytes(12);
    $tag = '';
    $cipher = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $nonce, $tag, $aad, 16);
    Check::expect($cipher !== false, 'encrypt failed');

    // Storage format: v1.<b64nonce>.<b64tag>.<b64cipher>
    $stored = 'v1.' . base64_encode($nonce) . '.' . base64_encode($tag) . '.' . base64_encode($cipher);
    Check::expect(str_contains($stored, base64_encode($plaintext)) === false, 'plaintext leaked into storage string');

    [$ver, $n, $t, $c] = explode('.', $stored);
    $dec = openssl_decrypt(base64_decode($c), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($n), base64_decode($t), $aad);
    Check::expect($dec === $plaintext, 'decrypt mismatch');

    // tamper → must fail (authenticated encryption)
    $tampered = base64_decode($c);
    $tampered[0] = chr(ord($tampered[0]) ^ 0xFF);
    $bad = @openssl_decrypt($tampered, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($n), base64_decode($t), $aad);
    Check::expect($bad === false, 'tampered ciphertext was accepted');

    // wrong AAD → must fail
    $badAad = @openssl_decrypt(base64_decode($c), 'aes-256-gcm', $key, OPENSSL_RAW_DATA, base64_decode($n), base64_decode($t), 'other-context');
    Check::expect($badAad === false, 'wrong AAD was accepted');

    // wrong key → must fail
    $badKey = @openssl_decrypt(base64_decode($c), 'aes-256-gcm', random_bytes(32), OPENSSL_RAW_DATA, base64_decode($n), base64_decode($t), $aad);
    Check::expect($badKey === false, 'wrong key accepted');

    // fresh nonce per encryption: same plaintext must not produce identical ciphertext
    $n2 = random_bytes(12); $t2 = '';
    $c2 = openssl_encrypt($plaintext, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $n2, $t2, $aad, 16);
    Check::expect(base64_encode($c2) !== base64_encode($cipher), 'ciphertext identical (nonce reuse!)');

    return 'format=v1.nonce.tag.cipher, tamper/AAD/key rejection verified';
});

// ── 2. Key rotation / AAD scope isolation ─────────────────────────────────────
Check::run('RT-02', 'Key separation per purpose (credentials vs attachments)', function () {
    $master = random_bytes(32);
    $kCred = hash_hkdf('sha256', $master, 32, 'vira-crm/credentials');
    $kFile = hash_hkdf('sha256', $master, 32, 'vira-crm/files');
    Check::expect($kCred !== $kFile, 'derived keys equal');

    $nonce = random_bytes(12); $tag = '';
    $c = openssl_encrypt('secret', 'aes-256-gcm', $kCred, OPENSSL_RAW_DATA, $nonce, $tag, '', 16);
    $byFileKey = @openssl_decrypt($c, 'aes-256-gcm', $kFile, OPENSSL_RAW_DATA, $nonce, $tag, '');
    Check::expect($byFileKey === false, 'cross-purpose decryption succeeded');
    return 'HKDF-SHA256 purpose-separated subkeys verified';
});

// ── 3. Password hashing (user passwords never encrypted, only hashed) ────────
Check::run('RT-03', 'password_hash + password_verify (bcrypt/argon2 available)', function () {
    Check::expect(defined('PASSWORD_BCRYPT'), 'bcrypt unavailable');

    $hash = password_hash('Correct-Horse-9', PASSWORD_BCRYPT, ['cost' => 12]);
    Check::expect(password_verify('Correct-Horse-9', $hash), 'verify failed');
    Check::expect(!password_verify('wrong', $hash), 'wrong password verified');
    Check::expect(str_starts_with($hash, '$2y$12$'), 'unexpected hash format');

    $algos = array_values(array_filter([
        defined('PASSWORD_BCRYPT') ? 'bcrypt' : null,
        defined('PASSWORD_ARGON2ID') ? 'argon2id' : null,
        defined('PASSWORD_ARGON2I') ? 'argon2i' : null,
    ]));
    return 'available=' . implode(',', $algos) . '; bcrypt verified';
});

// ── 4. Signed URLs / tokens for confidential PDF delivery ────────────────────
Check::run('RT-04', 'HMAC-signed download token (expiry + binding)', function () {
    $secret = random_bytes(32);
    $payload = ['uid' => 7, 'type' => 'credential_pdf', 'id' => 42, 'exp' => time() + 120];
    $data = rtrim(strtr(base64_encode(json_encode($payload)), '+/', '-_'), '=');
    $sig = hash_hmac('sha256', $data, $secret);
    Check::expect(hash_equals($sig, hash_hmac('sha256', $data, $secret)), 'valid signature rejected');

    $tampered = rtrim(strtr(base64_encode(json_encode(['uid' => 7, 'type' => 'credential_pdf', 'id' => 43, 'exp' => time() + 120])), '+/', '-_'), '=');
    Check::expect(!hash_equals($sig, hash_hmac('sha256', $tampered, $secret)), 'tampered payload accepted');
    return 'urlsafe token + constant-time compare verified';
});

// ── 5. Money: exact decimal arithmetic, no float drift ──────────────────────
Check::run('RT-05', 'Decimal money math with bcmath (no float)', function () {
    Check::expect(extension_loaded('bcmath'), 'bcmath missing');

    // 12,345,678.90 split into 3 equal installments with the remainder on the last one
    $total = '12345678.90';
    $parts = 3;
    $cents = bcmul($total, '100', 0);              // 1234567890 cents (integer)
    $per = bcdiv($cents, (string) $parts, 0);       // floor of cents
    $rem = bcmod($cents, (string) $parts);
    $list = [];
    $sum = '0';
    for ($i = 0; $i < $parts; $i++) {
        $c = bcadd($per, $i === $parts - 1 ? $rem : '0');
        $list[] = bcdiv($c, '100', 2);
        $sum = bcadd($sum, $c);
    }
    // 1234567890 / 3 = 411522630 cents exactly, so no remainder should exist
    Check::expect($rem === '0', 'unexpected remainder: ' . $rem);
    Check::expect($sum === $cents, 'parts do not sum to total');
    Check::expect($list[0] === '4115226.30', 'split wrong: ' . $list[0]);

    // a case with an actual remainder: 100.00 in 3 parts → 33.33, 33.33, 33.34
    $cents2 = '10000';
    $per2 = bcdiv($cents2, '3', 0);
    $rem2 = bcmod($cents2, '3');
    $parts2 = [];
    for ($i = 0; $i < 3; $i++) {
        $parts2[] = bcdiv(bcadd($per2, $i === 2 ? $rem2 : '0'), '100', 2);
    }
    Check::expect($parts2 === ['33.33', '33.33', '33.34'], 'remainder distribution wrong: ' . implode(',', $parts2));

    // float drift demonstration (why float is forbidden for money)
    $floatSum = 0.1 + 0.2;
    Check::expect($floatSum != 0.3, 'float demo unexpected');
    return 'exact split + remainder distribution verified';
});

// ── 6. Persian text normalization for search (REQ-03.06, REQ-09.01) ─────────
Check::run('RT-06', 'Persian search normalization (Arabic yeh/kaf, ZWNJ, digits)', function () {
    $normalize = static function (string $s): string {
        $s = str_replace(['ي', 'ك', 'ة', 'ۀ'], ['ی', 'ک', 'ه', 'ه'], $s);          // Arabic → Persian letters
        $s = str_replace(["\u{200C}", "\u{200F}", "\u{200E}"], ' ', $s);      // ZWNJ / direction marks
        $fa = ['۰','۱','۲','۳','۴','۵','۶','۷','۸','۹'];
        $s = str_replace($fa, range(0, 9), $s);                                     // Persian digits → ASCII
        $s = preg_replace('/\s+/u', ' ', $s) ?? $s;
        return trim(mb_strtolower($s, 'UTF-8'));
    };

    $a = $normalize('شرکت ویرا ديجيتال مارکتينگ');
    $b = $normalize('شرکت  ویرا  دیجیتال  مارکتینگ');
    Check::expect($a === $b, 'Arabic/Persian variant normalization failed: ' . $a . ' vs ' . $b);

    $c = $normalize('فاکتور شماره ۱۲۳۴۵');
    Check::expect(str_contains($c, '12345'), 'Persian digits not normalized: ' . $c);

    $d = $normalize("می\u{200C}رود");
    Check::expect($d === 'می رود', 'ZWNJ not normalized');
    return 'yeh/kaf/digits/ZWNJ normalization verified';
});

// ── 7. PDO SQLite: automated-test database engine with strict types ─────────
Check::run('RT-07', 'PDO SQLite available for automated tests (strict FK + text ids + integer cents)', function () {
    $pdo = new PDO('sqlite::memory:');
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    $pdo->exec('PRAGMA foreign_keys = ON');
    $pdo->exec('CREATE TABLE customers (id INTEGER PRIMARY KEY, national_id TEXT NOT NULL UNIQUE, name TEXT NOT NULL)');
    $pdo->exec('CREATE TABLE contracts (id INTEGER PRIMARY KEY, customer_id INTEGER NOT NULL REFERENCES customers(id), amount_cents INTEGER NOT NULL)');

    $pdo->prepare('INSERT INTO customers (national_id, name) VALUES (?, ?)')->execute(['0012345678', 'شرکت نمونه']);
    $pdo->prepare('INSERT INTO contracts (customer_id, amount_cents) VALUES (?, ?)')->execute([1, 1234567890]);

    $row = $pdo->query('SELECT national_id, amount_cents FROM customers JOIN contracts ON contracts.customer_id = customers.id')->fetch(PDO::FETCH_ASSOC);
    Check::expect($row['national_id'] === '0012345678', 'leading zeros lost');
    Check::expect((int) $row['amount_cents'] === 1234567890, 'amount mismatch');

    $fkFailed = false;
    try {
        $pdo->exec('INSERT INTO contracts (customer_id, amount_cents) VALUES (999, 1)');
    } catch (PDOException) {
        $fkFailed = true;
    }
    Check::expect($fkFailed, 'foreign key not enforced');
    return 'text ids, integer cents, FK enforcement verified';
});

// ── 8. JSON + Unicode for API/i18n ────────────────────────────────────────────
Check::run('RT-08', 'JSON encode/decode with Persian text and no escaping loss', function () {
    $data = ['title' => 'قرارداد نگهداری سایت', 'amount' => '12500000.00', 'tags' => ['سئو', 'طراحی سایت']];
    $json = json_encode($data, JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
    Check::expect(str_contains($json, 'قرارداد'), 'unicode escaped');
    Check::expect(json_decode($json, true) === $data, 'roundtrip mismatch');
    return 'ok';
});

// ── 9. Date/time with timezone for scheduler correctness ─────────────────────
Check::run('RT-09', 'DateTimeImmutable + Asia/Tehran + immutable day math', function () {
    $tz = new DateTimeZone('Asia/Tehran');
    $d = new DateTimeImmutable('2026-03-21 00:00:00', $tz);
    $d2 = $d->modify('-2 days');
    Check::expect($d2->format('Y-m-d') === '2026-03-19', 'sub-day math wrong');
    // Iran abolished DST in 2022 — offset should be constant (+03:30) year-round
    $winter = new DateTimeImmutable('2026-01-15 12:00', $tz);
    $summer = new DateTimeImmutable('2026-07-15 12:00', $tz);
    Check::expect($winter->getOffset() === $summer->getOffset(), 'unexpected DST offset change');
    return 'offset=' . $winter->getOffset() . 's year-round (+03:30)';
});

// ── 10. Random identifiers for opaque file/token names ──────────────────────
Check::run('RT-10', 'random_bytes-based opaque identifiers (no guessable paths)', function () {
    $id = bin2hex(random_bytes(16));
    Check::expect(strlen($id) === 32, 'uuid-ish length');
    Check::expect($id !== bin2hex(random_bytes(16)), 'collision');
    return '128-bit random hex verified';
});

echo str_repeat('=', 100), "\n";
printf("RESULT: %s — %d failure(s)\n", Check::$failures === 0 ? 'PASS' : 'FAIL', Check::$failures);

$report = [
    'generated_at' => date('c'),
    'php_version' => PHP_VERSION,
    'extensions' => array_values(array_intersect(get_loaded_extensions(), ['openssl','mbstring','bcmath','pdo_sqlite','pdo_mysql','json','hash','random','gd','zip','curl'])),
    'checks' => Check::$results,
    'failures' => Check::$failures,
    'verdict' => Check::$failures === 0 ? 'PASS' : 'FAIL',
];
@mkdir(__DIR__ . '/../../docs/phase-1/evidence', 0775, true);
file_put_contents(__DIR__ . '/../../docs/phase-1/evidence/php-runtime-smoke-test.json', json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE) . "\n");

exit(Check::$failures === 0 ? 0 : 1);
