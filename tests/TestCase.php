<?php

namespace Tests;

use App\Providers\WorkbenchServiceProvider;
use Comhon\EntityRequester\EntityRequesterServiceProvider;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Schema;
use Orchestra\Testbench\TestCase as Orchestra;

class TestCase extends Orchestra
{
    protected function setUp(): void
    {
        parent::setUp();

        Factory::guessFactoryNamesUsing(
            fn (string $modelName) => 'Comhon\\EntityRequester\\Database\\Factories\\'.class_basename($modelName).'Factory'
        );
    }

    protected function getPackageProviders($app)
    {
        return [
            EntityRequesterServiceProvider::class,
            WorkbenchServiceProvider::class,
        ];
    }

    public function getEnvironmentSetUp($app)
    {
        config()->set('database.default', 'testing');

        if (! Schema::hasTable('users')) {
            foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__.'/../workbench/database/migrations') as $migration) {
                (include $migration->getRealPath())->up();
            }
        }
    }

    public static function providerBoolean()
    {
        return [
            [true],
            [false],
        ];
    }
}
