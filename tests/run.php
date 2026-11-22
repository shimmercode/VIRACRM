<?php

/**
 * VIRA CRM test runner.
 *
 * Discovers tests/{Unit,Feature}/*Test.php, runs every ViraTestCase subclass,
 * and prints a machine-readable summary plus a JUnit-ish JSON report that the
 * docs generator consumes.
 *
 * Usage:
 *   php tests/run.php                 # everything
 *   php tests/run.php Unit            # one suite
 *   php tests/run.php --json=path     # write JSON report
 */

require dirname(__DIR__) . '/vendor/autoload.php';
require __DIR__ . '/ViraTestCase.php';
require __DIR__ . '/support/DatabaseHarness.php';

use Tests\ViraTestCase;

$suites = ['Unit', 'Feature'];
$reportPath = null;
$onlySuite = null;

foreach (array_slice($argv, 1) as $arg) {
    if (str_starts_with($arg, '--json=')) {
        $reportPath = substr($arg, 7);
    } elseif (str_starts_with($arg, '--filter=')) {
        $onlySuite = substr($arg, 9);
    } elseif (in_array($arg, $suites, true)) {
        $onlySuite = $arg;
    }
}

$root = dirname(__DIR__);
$results = [];
$totals = ['tests' => 0, 'passed' => 0, 'failed' => 0, 'assertions' => 0, 'duration_ms' => 0.0];

foreach ($suites as $suite) {
    if ($onlySuite !== null && $onlySuite !== $suite) {
        continue;
    }

    $dir = __DIR__ . '/' . $suite;
    if (! is_dir($dir)) {
        continue;
    }

    $files = glob($dir . '/*Test.php') ?: [];
    sort($files);

    foreach ($files as $file) {
        $before = get_declared_classes();
        require_once $file;
        $after = get_declared_classes();

        foreach (array_diff($after, $before) as $class) {
            if (! is_subclass_of($class, ViraTestCase::class)) {
                continue;
            }

            /** @var ViraTestCase $instance */
            $instance = new $class();
            $outcome = $instance->execute();
            $passed = $outcome['failures'] === [];

            $totals['tests']++;
            $totals['assertions'] += $outcome['assertions'];
            $totals['duration_ms'] += $outcome['duration_ms'];
            $passed ? $totals['passed']++ : $totals['failed']++;

            $results[] = [
                'suite' => $suite,
                'class' => $class,
                'short' => substr(strrchr('\\' . $class, '\\'), 1),
                'passed' => $passed,
                'assertions' => $outcome['assertions'],
                'failures' => $outcome['failures'],
                'duration_ms' => $outcome['duration_ms'],
            ];

            $marker = $passed ? '  ok  ' : ' FAIL ';
            printf(
                "%s %-46s %3d assertions\n",
                $marker,
                substr(strrchr('\\' . $class, '\\'), 1),
                $outcome['assertions']
            );
            foreach ($outcome['failures'] as $failure) {
                echo '        ✗ ' . $failure . "\n";
            }
        }
    }
}

echo "\n──────────────────────────────────────────────────────────────\n";
printf(
    "tests: %d | passed: %d | failed: %d | assertions: %d | %.0f ms\n",
    $totals['tests'],
    $totals['passed'],
    $totals['failed'],
    $totals['assertions'],
    $totals['duration_ms']
);
echo "RELATIONSHIP TO PHPUNIT: these tests use the project's ViraTestCase shim\n";
echo "because Composer/Packagist is unreachable in this environment. The test\n";
echo "bodies are written against PHPUnit-compatible assertions.\n";

if ($reportPath !== null) {
    file_put_contents($reportPath, json_encode([
        'generated_at' => date('c'),
        'environment' => [
            'php' => PHP_VERSION,
            'laravel' => Illuminate\Foundation\Application::VERSION,
            'db_driver' => 'sqlite',
            'runner' => 'tests/run.php (ViraTestCase shim)',
        ],
        'totals' => $totals,
        'results' => $results,
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES));
    echo "report written: {$reportPath}\n";
}

exit($totals['failed'] > 0 ? 1 : 0);
