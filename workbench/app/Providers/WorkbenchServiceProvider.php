<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\Purchase;
use App\Models\Tag;
use App\Models\User;
use App\Resolver\ModelResolver;
use App\Visible;
use Comhon\EntityRequester\Commands\MakeModelSchema;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Database\Eloquent\Relations\Relation;
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
                ->bind('visible', Visible::class)
                ->bind('purchase', Purchase::class)
                ->bind('tag', Tag::class);
        });
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        $resolver = fn ($type) => $type == 'hashed' ? ['type' => 'string'] : null;
        MakeModelSchema::registerColumnTypeResolver($resolver);
        MakeModelSchema::registerCastTypeResolver($resolver);

        $resolver = fn (ReflectionParameter $param) => $param->getName() == 'resolvableParam'
            ? ['type' => 'string'] : null;
        MakeModelSchema::registerParamTypeResolver($resolver);

        $workbenchDir = dirname(__DIR__, 2);
        config([
            'entity-requester.entity_schema_directory' => $workbenchDir.'/schemas/entities',
            'entity-requester.request_schema_directory' => $workbenchDir.'/schemas/requests',
        ]);

        Relation::enforceMorphMap([
            'user' => User::class,
            'post' => Post::class,
            'visible' => Visible::class,
            'purchase' => Purchase::class,
            'tag' => Tag::class,
        ]);
    }
}
