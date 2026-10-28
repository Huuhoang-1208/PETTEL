<?php

namespace Tests;

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Testing\TestCase as BaseTestCase;
use RuntimeException;

abstract class TestCase extends BaseTestCase
{
    public function createApplication(): Application
    {
        $app = parent::createApplication();
        $connection = $app->make('db')->connection();

        if (
            ! $app->environment('testing')
            || $connection->getDriverName() !== 'mysql'
            || $connection->getDatabaseName() !== 'pettel_test'
            || $connection->getConfig('username') !== 'pettel_test'
        ) {
            throw new RuntimeException(
                'Tests must use the dedicated pettel_test MySQL database.'
            );
        }

        return $app;
    }
}
