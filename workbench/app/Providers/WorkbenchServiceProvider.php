<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\User;
use App\Resolver\ModelResolver;
use App\Visible;
use Comhon\EntityRequester\Commands\MakeModelSchema;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Support\ServiceProvider;
use ReflectionParameter;

class WorkbenchServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(ModelResolverInterface::class, function () {
            return (new ModelResolver)
                ->bind('user', User::class)
                ->bind('post', Post::class)
                ->bind('visible', Visible::class);
        });

        $reslover = fn ($type) => $type == 'hashed' ? ['type' => 'string'] : null;
        MakeModelSchema::registerColumnTypeResolver($reslover);
        MakeModelSchema::registerCastTypeResolver($reslover);

        $reslover = fn (ReflectionParameter $param) => $param->getName() == 'resolvableParam'
            ? ['type' => 'string'] : null;
        MakeModelSchema::registerParamTypeResolver($reslover);
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
