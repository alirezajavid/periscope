<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        // Tests send `Referer: http://localhost`; make that a stateful SPA origin whatever .env says.
        config(['sanctum.stateful' => ['localhost']]);
    }

    /**
     * RefreshDatabase runs migrate:fresh, so refuse to boot against anything
     * that is not a dedicated test database.
     */
    public function createApplication(): Application
    {
        $app = parent::createApplication();

        $connection = $app['config']['database.default'];
        $database = (string) $app['config']["database.connections.{$connection}.database"];

        if ($connection !== 'sqlite' && ! str_ends_with($database, '_test')) {
            throw new RuntimeException(
                "Refusing to run tests against database \"{$database}\": its name must end with \"_test\". "
                .'Set DB_DATABASE=periscope_test in api/.env.testing.'
            );
        }

        return $app;
    }
}
