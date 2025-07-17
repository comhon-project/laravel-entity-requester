<?php

namespace Comhon\EntityRequester;

use Comhon\EntityRequester\Commands\MakeModelSchema;
use Spatie\LaravelPackageTools\Package;
use Spatie\LaravelPackageTools\PackageServiceProvider;

class EntityRequesterServiceProvider extends PackageServiceProvider
{
    public function configurePackage(Package $package): void
    {
        /*
         * This class is a Package Service Provider
         *
         * More info: https://github.com/spatie/laravel-package-tools
         */
        $package
            ->name('laravel-entity-requester')
            ->hasConfigFile()
            ->hasCommand(MakeModelSchema::class);
    }
}
