<?php

namespace Yezper\LaravelCustomFieldsFilament\Tests;

use Orchestra\Testbench\TestCase as Orchestra;
use Yezper\LaravelCustomFields\CustomFieldsServiceProvider;
use Yezper\LaravelCustomFieldsFilament\CustomFieldsFilamentServiceProvider;

class TestCase extends Orchestra
{
    /** @return array<int, class-string> */
    protected function getPackageProviders($app): array
    {
        return [
            CustomFieldsServiceProvider::class,
            CustomFieldsFilamentServiceProvider::class,
        ];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.timezone', 'UTC');
    }
}
