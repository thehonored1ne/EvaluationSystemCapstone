<?php

namespace Tests;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use Illuminate\Support\Facades\DB;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    /**
     * Creates the application.
     */
    public function createApplication()
    {
        // 1. Never allow cached configuration from development to contaminate tests
        $cachedConfig = __DIR__.'/../bootstrap/cache/config.php';
        if (file_exists($cachedConfig)) {
            @unlink($cachedConfig);
        }

        $app = require __DIR__.'/../bootstrap/app.php';

        $app->make(Kernel::class)->bootstrap();

        // 2. Ensure testing database file exists
        $testingDb = database_path('testing.sqlite');
        if (! file_exists($testingDb)) {
            touch($testingDb);
        }

        // 3. Strictly force testing database configuration in memory
        config([
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $testingDb,
        ]);

        DB::purge();

        return $app;
    }

    /**
     * Safety hook invoked before RefreshDatabase runs migrate:fresh.
     */
    protected function beforeRefreshingDatabase()
    {
        $activeDb = config('database.connections.sqlite.database');
        $devDb = database_path('database.sqlite');

        $resolvedActive = realpath($activeDb) ?: $activeDb;
        $resolvedDev = realpath($devDb) ?: $devDb;

        if (
            $resolvedActive === $resolvedDev ||
            $activeDb === $devDb ||
            str_ends_with(str_replace('\\', '/', (string) $activeDb), 'database/database.sqlite')
        ) {
            throw new RuntimeException(
                "CRITICAL SAFETY GUARD: RefreshDatabase attempted to wipe development database [{$activeDb}]. Aborting to prevent data loss."
            );
        }
    }
}
