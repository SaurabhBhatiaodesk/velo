<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\File;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     *
     * @return void
     */
    public function register()
    {
        //
    }

    /**
     * Bootstrap any application services.
     *
     * @return void
     */
    public function boot()
    {
        $this->loadMigrationsFrom(array_merge([database_path('migrations')], glob(database_path('migrations') . '/*', GLOB_ONLYDIR)));
        if (strpos(config('database.connections.mysql.host'), 'rds.amazonaws.com') !== false) {
            DB::connection()->getPdo()->exec("SET @@aurora_replica_read_consistency = 'session';");
        }
    }
}
