<?php

namespace Comhon\EntityRequester;

use Comhon\EntityRequester\Commands\MakeModelSchema;
use Comhon\EntityRequester\EntityRequest\AccessValidator;
use Comhon\EntityRequester\Factories\RequestAccessFactory;
use Comhon\EntityRequester\Factories\SchemaFactory;
use Comhon\EntityRequester\Interfaces\AccessValidatorInterface;
use Comhon\EntityRequester\Interfaces\RequestAccessFactoryInterface;
use Comhon\EntityRequester\Interfaces\SchemaFactoryInterface;
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

    public function packageRegistered()
    {
        $this->app->singletonIf(SchemaFactoryInterface::class, SchemaFactory::class);
        $this->app->singletonIf(RequestAccessFactoryInterface::class, RequestAccessFactory::class);
        $this->app->singletonIf(AccessValidatorInterface::class, AccessValidator::class);
    }
}
