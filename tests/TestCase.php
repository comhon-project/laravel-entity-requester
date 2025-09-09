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
            fn (string $modelName) => 'Database\\Factories\\'.class_basename($modelName).'Factory'
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
        if (! Schema::hasTable('users')) {
            foreach (\Illuminate\Support\Facades\File::allFiles(__DIR__.'/../workbench/database/Migrations') as $migration) {
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

    protected function getRawSqlAccordingDriver(string $rawSql): string
    {
        $connectionName = config('database.default');
        $driver = config("database.connections.{$connectionName}.driver");

        if ($driver != 'pgsql' && $driver != 'sqlite') {
            $rawSql = str_replace('"', '`', $rawSql);
        }
        if ($driver == 'pgsql') {
            $rawSql = str_replace(
                ['" LIKE ', '" NOT LIKE ', '" ILIKE ', '" NOT ILIKE '],
                ['"::text LIKE ', '"::text NOT LIKE ', '"::text ILIKE ', '"::text NOT ILIKE '],
                $rawSql
            );
        }

        return $rawSql;
    }
}
