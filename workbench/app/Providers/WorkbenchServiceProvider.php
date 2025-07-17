<?php

namespace App\Providers;

use App\Models\Post;
use App\Models\User;
use App\Resolver\ModelResolver;
use App\Visible;
use Comhon\ModelResolverContract\ModelResolverInterface;
use Illuminate\Support\ServiceProvider;

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
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {
        //
    }
}
