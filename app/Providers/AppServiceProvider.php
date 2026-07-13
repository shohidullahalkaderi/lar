<?php

namespace App\Providers;

use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        //
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        // The Safety Check: Stops execution early if the Redis password is empty
        if (empty(config('database.redis.default.password'))) {
            throw new \RuntimeException("REDIS_PASSWORD must be defined in your .env file.");
        }
    }
}
