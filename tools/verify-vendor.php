<?php
/**
 * VIRA CRM — vendor/ + framework smoke verification.
 *
 * Confirms the development vendor tree can actually boot Laravel and exercise
 * the subsystems the application depends on. Run before trusting any test result.
 */
$root = dirname(__DIR__);
require $root . '/vendor/autoload.php';

$checks = [];
$record = function (string $name, callable $fn) use (&$checks) {
    try {
        $value = $fn();
        $checks[] = ['check' => $name, 'ok' => true, 'detail' => $value];
    } catch (Throwable $e) {
        $checks[] = ['check' => $name, 'ok' => false, 'detail' => get_class($e) . ': ' . $e->getMessage()];
    }
};

$record('framework class loads', fn() => class_exists(Illuminate\Foundation\Application::class) ? 'yes' : 'no');
$record('laravel version', fn() => Illuminate\Foundation\Application::VERSION);
$record('carbon loads', fn() => \Carbon\CarbonImmutable::now('Asia/Tehran')->format('Y-m-d H:i'));
$record('container resolves', function () {
    $c = new Illuminate\Container\Container();
    $c->bind('svc', fn() => 'bound-value');
    return $c->make('svc');
});
$record('schema builder', function () {
    $capsule = new Illuminate\Database\Capsule\Manager();
    $capsule->addConnection(['driver' => 'sqlite', 'database' => ':memory:', 'prefix' => '']);
    $capsule->setAsGlobal();
    $capsule->bootEloquent();
    $schema = $capsule->getConnection()->getSchemaBuilder();
    $schema->create('probe', function ($t) {
        $t->id();
        $t->string('label')->nullable();
        $t->timestamps();
    });
    $capsule->getConnection()->table('probe')->insert(['label' => 'مشتری آزمایشی']);
    return $capsule->getConnection()->table('probe')->value('label');
});
$record('validator', function () {
    $factory = new Illuminate\Validation\Factory(
        new Illuminate\Translation\Translator(new Illuminate\Translation\ArrayLoader(), 'fa')
    );
    $v = $factory->make(['mobile' => ''], ['mobile' => 'required']);
    return $v->fails() ? 'rejects invalid input' : 'ACCEPTED INVALID INPUT';
});
$record('blade compiles+renders', function () {
    $files = new Illuminate\Filesystem\Filesystem();
    $cache = sys_get_temp_dir() . '/vira-blade-' . getmypid();
    @mkdir($cache, 0777, true);
    $compiler = new Illuminate\View\Compilers\BladeCompiler($files, $cache);
    $php = $compiler->compileString('<?php $__env->startSection("x"); ?>سلام {{ $name }}<?php $__env->stopSection(); ?>');
    return str_contains($php, 'سلام') ? 'compiled ok' : 'compile produced unexpected output';
});
$record('mbstring persian', fn() => mb_strlen('مشتری حقوقی') . ' chars, ' . mb_substr('مشتری حقوقی', 0, 5) . ' prefix');

$failures = array_filter($checks, fn($c) => !$c['ok']);
$out = [
    'php' => PHP_VERSION,
    'vendor_packages' => count(array_filter(scandir($root . '/vendor'), fn($d) => is_dir($root . '/vendor/' . $d) && $d !== '.' && $d !== '..')),
    'checks' => $checks,
    'result' => $failures ? 'FAIL' : 'PASS',
];
echo json_encode($out, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), "\n";
exit($failures ? 1 : 0);
