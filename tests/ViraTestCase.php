<?php

namespace Tests;

use Throwable;

/**
 * Minimal test case.
 *
 * WHY NOT PHPUNIT: in this environment Packagist is unreachable, so PHPUnit's
 * ~20 transitive packages cannot be installed. Rather than claim tests ran when
 * they didn't, we ship a small runner that supports the assertions this project
 * actually needs. On the client host PHPUnit is installed with Composer and
 * these same test bodies run under it unchanged — the assertion methods mirror
 * PHPUnit's signatures on purpose.
 */
abstract class ViraTestCase
{
    public int $assertions = 0;

    /** @var list<string> */
    private array $failures = [];

    abstract public function run(): void;

    protected function setUp(): void
    {
        //
    }

    public function execute(): array
    {
        $this->assertions = 0;
        $this->failures = [];
        $start = microtime(true);

        try {
            $this->setUp();
            $this->run();
        } catch (Throwable $e) {
            $trace = collect($e->getTrace())->take(4)->map(
                fn ($f) => basename($f['file'] ?? '?') . ':' . ($f['line'] ?? '?')
                    . ' ' . ($f['class'] ?? '') . '::' . ($f['function'] ?? '')
            )->implode(' < ');
            $this->failures[] = sprintf(
                '%s: %s (%s:%d)  via %s',
                get_class($e),
                $e->getMessage(),
                basename($e->getFile()),
                $e->getLine(),
                $trace
            );
        }

        return [
            'assertions' => $this->assertions,
            'failures' => $this->failures,
            'duration_ms' => round((microtime(true) - $start) * 1000, 2),
        ];
    }

    private function fail(string $message, string $detail = ''): void
    {
        $this->failures[] = $detail === '' ? $message : "{$message} — {$detail}";
    }

    public function assertTrue(bool $condition, string $message = 'Expected true'): void
    {
        $this->assertions++;
        if (! $condition) {
            $this->fail($message);
        }
    }

    public function assertFalse(bool $condition, string $message = 'Expected false'): void
    {
        $this->assertTrue(! $condition, $message);
    }

    public function assertSame(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected !== $actual) {
            $this->fail(
                $message !== '' ? $message : 'Values are not identical',
                'expected ' . $this->stringify($expected) . ', got ' . $this->stringify($actual)
            );
        }
    }

    public function assertEquals(mixed $expected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($expected != $actual) {
            $this->fail(
                $message !== '' ? $message : 'Values are not equal',
                'expected ' . $this->stringify($expected) . ', got ' . $this->stringify($actual)
            );
        }
    }

    public function assertNull(mixed $actual, string $message = 'Expected null'): void
    {
        $this->assertSame(null, $actual, $message);
    }

    public function assertNotNull(mixed $actual, string $message = 'Expected not null'): void
    {
        $this->assertions++;
        if ($actual === null) {
            $this->fail($message);
        }
    }

    public function assertContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (! str_contains($haystack, $needle)) {
            $this->fail(
                $message !== '' ? $message : 'String does not contain expected fragment',
                "needle '{$needle}' in '{$haystack}'"
            );
        }
    }

    public function assertNotContains(string $needle, string $haystack, string $message = ''): void
    {
        $this->assertions++;
        if (str_contains($haystack, $needle)) {
            $this->fail(
                $message !== '' ? $message : 'String unexpectedly contains fragment',
                "needle '{$needle}' in '{$haystack}'"
            );
        }
    }

    public function assertMatches(string $pattern, string $subject, string $message = ''): void
    {
        $this->assertions++;
        if (! preg_match($pattern, $subject)) {
            $this->fail(
                $message !== '' ? $message : 'Subject does not match pattern',
                "pattern {$pattern} against '{$subject}'"
            );
        }
    }

    /** Accepts arrays and any Countable (Eloquent collections included). */
    public function assertCount(int $expected, array|\Countable $actual, string $message = ''): void
    {
        $this->assertSame($expected, count($actual), $message !== '' ? $message : 'Unexpected item count');
    }

    public function assertThrows(string $expectedClass, callable $fn, string $message = ''): void
    {
        $this->assertions++;
        try {
            $fn();
        } catch (Throwable $e) {
            if ($e instanceof $expectedClass) {
                return;
            }
            $this->fail(
                $message !== '' ? $message : 'Wrong exception type',
                'expected ' . $expectedClass . ', got ' . get_class($e)
            );

            return;
        }
        $this->fail($message !== '' ? $message : 'Expected exception was not thrown', $expectedClass);
    }

    public function assertGreaterThan(float|int $expected, float|int $actual, string $message = ''): void
    {
        $this->assertions++;
        if (! ($actual > $expected)) {
            $this->fail($message !== '' ? $message : 'Value is not greater', "expected > {$expected}, got {$actual}");
        }
    }

    public function assertNotSame(mixed $unexpected, mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if ($unexpected === $actual) {
            $this->fail($message !== '' ? $message : 'Expected values to differ, both were ' . $this->describe($actual));
        }
    }

    public function assertIsArray(mixed $actual, string $message = ''): void
    {
        $this->assertions++;
        if (! is_array($actual)) {
            $this->fail($message !== '' ? $message : 'Expected array, got ' . get_debug_type($actual));
        }
    }

    public function assertArrayHasKey(string|int $key, array $array, string $message = ''): void
    {
        $this->assertions++;
        if (! array_key_exists($key, $array)) {
            $this->fail(
                $message !== '' ? $message : 'Expected key ' . $key . ', have: ' . implode(', ', array_keys($array))
            );
        }
    }

    /**
     * Reads through the active connection — the same SQLite file the test
     * wrote to. Not a mock.
     */
    public function assertDatabaseHas(string $table, array $criteria, string $message = ''): void
    {
        $this->assertions++;

        $query = \Illuminate\Support\Facades\DB::table($table);
        foreach ($criteria as $column => $value) {
            $query->where($column, '=', $value);
        }

        if (! $query->exists()) {
            $this->fail(
                $message !== '' ? $message : "No row in {$table} matching " . json_encode($criteria, JSON_UNESCAPED_UNICODE)
            );
        }
    }

    public function assertLessThan(float|int $expected, float|int $actual, string $message = ''): void
    {
        $this->assertions++;
        if (! ($actual < $expected)) {
            $this->fail($message !== '' ? $message : 'Value is not less', "expected < {$expected}, got {$actual}");
        }
    }

    private function stringify(mixed $value): string
    {
        if (is_string($value)) {
            return "'" . $value . "'";
        }
        if (is_bool($value)) {
            return $value ? 'true' : 'false';
        }
        if ($value === null) {
            return 'null';
        }

        return (string) (is_scalar($value) ? $value : json_encode($value, JSON_UNESCAPED_UNICODE));
    }
}
