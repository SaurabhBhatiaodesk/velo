<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use App\Broadcasting\PusherBroadcaster;

class BroadcastServiceProvider extends ServiceProvider
{
    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->app->singleton('broadcast', function ($app) {
            return new PusherBroadcaster($app['events']);
        });

        require base_path('routes/channels.php');
    }
}
