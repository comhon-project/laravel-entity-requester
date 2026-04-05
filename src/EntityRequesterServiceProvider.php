<?php

namespace Comhon\EntityRequester;

use Comhon\EntityRequester\Commands\MakeModelSchema;
use Comhon\EntityRequester\Condition\OperatorManager;
use Comhon\EntityRequester\EntityRequest\Authorizer;
use Comhon\EntityRequester\Factories\Schema\EntitySchemaFactory;
use Comhon\EntityRequester\Factories\Schema\EnumSchemaFactory;
use Comhon\EntityRequester\Factories\Schema\RequestSchemaFactory;
use Comhon\EntityRequester\Interfaces\ConditionOperatorManagerInterface;
use Comhon\EntityRequester\Interfaces\EntityRequestAuthorizerInterface;
use Comhon\EntityRequester\Interfaces\EntitySchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\EnumSchemaFactoryInterface;
use Comhon\EntityRequester\Interfaces\RequestSchemaFactoryInterface;
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
        $this->app->singletonIf(ConditionOperatorManagerInterface::class, OperatorManager::class);
        $this->app->singletonIf(EntitySchemaFactoryInterface::class, EntitySchemaFactory::class);
        $this->app->singletonIf(RequestSchemaFactoryInterface::class, RequestSchemaFactory::class);
        $this->app->singletonIf(EnumSchemaFactoryInterface::class, EnumSchemaFactory::class);
        $this->app->singletonIf(EntityRequestAuthorizerInterface::class, Authorizer::class);
    }
}
