<?php

namespace Tests\Support;

use Illuminate\Config\Repository;
use Illuminate\Container\Container;
use Illuminate\Hashing\HashManager;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\Connection;
use Illuminate\Database\ConnectionInterface;
use Illuminate\Database\DatabaseTransactionsManager;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Events\Dispatcher;
use Illuminate\Support\Facades\Facade;
use Illuminate\Support\Facades\Schema;

/**
 * Boots a real database + Eloquent stack for tests.
 *
 * WHY A HARNESS AND NOT `DatabaseTestCase`: PHPUnit and Laravel's own testing
 * helpers are unavailable here (Packagist unreachable), so each test class
 * would otherwise repeat this wiring — and repeat it slightly wrong.
 *
 * A REAL BUG THIS FIXES: `Capsule::bootEloquent()` only installs an Eloquent
 * event dispatcher *if one was set first*:
 *
 *     if ($dispatcher = $this->getEventDispatcher()) {
 *         Eloquent::setEventDispatcher($dispatcher);
 *     }
 *
 * Without a dispatcher, `static::saving(...)` inside a model's `booted()`
 * silently registers nothing. Model hooks then never run — the code looks
 * correct, the rows are written, and derived columns stay null. That is exactly
 * the kind of defect that reaches production. The harness always installs a
 * dispatcher so model events behave the way they do in a real Laravel app.
 */
final class DatabaseHarness
{
    public Capsule $capsule;
    public Connection $connection;
    public string $path;

    public static function boot(string $name = 'test'): self
    {
        $harness = new self();
        $harness->path = sys_get_temp_dir() . "/vira-{$name}-" . getmypid() . '-' . bin2hex(random_bytes(4)) . '.sqlite';

        touch($harness->path);

        $capsule = new Capsule();
        $capsule->addConnection([
            'driver' => 'sqlite',
            'database' => $harness->path,
            'prefix' => '',
            'foreign_key_constraints' => true,
        ]);

        // Install the dispatcher BEFORE bootEloquent(), otherwise model events
        // never fire (see class docblock).
        $capsule->setEventDispatcher(new Dispatcher(new Container()));
        $capsule->setAsGlobal();
        $capsule->bootEloquent();

        // Eloquent remembers which models it has already booted, and a model's
        // `booted()` registers its observers on the dispatcher that was active
        // *at that moment*. Booting a second harness would therefore leave every
        // previously-booted model wired to the previous (now stale) dispatcher,
        // so its saving/creating hooks silently stop firing. Clearing the booted
        // registry forces each model to re-register against the current one.
        Model::clearBootedModels();

        $connection = $capsule->getConnection();
        $connection->getPdo()->exec('PRAGMA foreign_keys = ON');

        $container = new Container();
        $container->instance('config', new Repository([
            'database' => [
                'default' => 'sqlite',
                'connections' => ['sqlite' => ['driver' => 'sqlite', 'database' => $harness->path]],
            ],
            'app' => ['timezone' => 'Asia/Tehran'],
        ]));
        $container->instance('db', $capsule->getDatabaseManager());
        $container->instance('db.connection', $connection);
        $container->bind('db.schema', fn () => $connection->getSchemaBuilder());
        $container->bind('db.transactions', fn () => new DatabaseTransactionsManager());
        $container->instance(ConnectionInterface::class, $connection);
        $container->instance(Connection::class, $connection);
        $container->instance('events', new Dispatcher($container));

        // The `hashed` model cast and Hash::make() resolve the `hash` binding.
        // Without it, any test that creates a user dies with "Target class
        // [hash] does not exist" — a harness gap, not an application bug.
        $container->instance('hash', new HashManager($container));

        Facade::setFacadeApplication($container);
        Schema::clearResolvedInstances();

        $harness->capsule = $capsule;
        $harness->connection = $connection;

        return $harness;
    }

    /** Run every migration in filename order, exactly as the app would. */
    public function migrate(): self
    {
        $root = dirname(__DIR__, 2);
        $files = glob($root . '/database/migrations/*.php');
        sort($files);

        foreach ($files as $file) {
            (require $file)->up();
        }

        return $this;
    }

    public function destroy(): void
    {
        if (is_file($this->path)) {
            @unlink($this->path);
        }
        if (is_file($this->path . '-wal')) {
            @unlink($this->path . '-wal');
        }
        if (is_file($this->path . '-shm')) {
            @unlink($this->path . '-shm');
        }
    }
}
