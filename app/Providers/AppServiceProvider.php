<?php

namespace App\Providers;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Symfony\Component\HttpFoundation\Response;

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

        // 1. Login Throttle: 5 requests per minute per email + IP
        RateLimiter::for('login', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(5)
                ->by($email . '|' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;
                    return response()->json([
                        'detail' => "Too many requests. Please try again in {$retryAfter} seconds."
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers);
                });
        });

        // 2. Register Throttle: 10 requests per minute per email + IP
        RateLimiter::for('register', function (Request $request) {
            $email = strtolower(trim((string) $request->input('email')));

            return Limit::perMinute(10)
                ->by($email . '|' . $request->ip())
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;
                    return response()->json([
                        'detail' => "Too many requests. Please try again in {$retryAfter} seconds."
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers);
                });
        });

        // 3. Logout Throttle: 10 requests per minute per Token (or IP fallback)
        RateLimiter::for('logout', function (Request $request) {
            $token = $request->bearerToken() ?: $request->ip();

            return Limit::perMinute(10)
                ->by($token)
                ->response(function (Request $request, array $headers) {
                    $retryAfter = $headers['Retry-After'] ?? 60;
                    return response()->json([
                        'detail' => "Too many requests. Please try again in {$retryAfter} seconds."
                    ], Response::HTTP_TOO_MANY_REQUESTS, $headers);
                });
        });
    }
}