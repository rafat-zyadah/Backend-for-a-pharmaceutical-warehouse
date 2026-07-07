<?php

namespace Tests;

use Illuminate\Foundation\Testing\TestCase as BaseTestCase;

abstract class TestCase extends BaseTestCase
{
    protected function tearDown(): void
    {
        if ($this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }

        parent::tearDown();
    }

    protected function resetAuthGuards(): void
    {
        if ($this->app->bound('auth')) {
            $this->app['auth']->forgetGuards();
        }
    }
}
