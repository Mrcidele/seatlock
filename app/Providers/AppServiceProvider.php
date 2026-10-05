<?php

declare(strict_types=1);

namespace App\Providers;

use App\SeatLock\RedisSeatLockService;
use App\SeatLock\ResilientSeatLockService;
use App\SeatLock\SeatLockService;
use Carbon\CarbonImmutable;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Contracts\Redis\Factory as RedisFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Psr\Log\LoggerInterface;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->singleton(SeatLockService::class, function (Application $app): SeatLockService {
            $connection = config('seatlock.locks.redis_connection');
            $redis = new RedisSeatLockService(
                $app->make(RedisFactory::class),
                is_string($connection) ? $connection : 'locks',
            );

            return config('seatlock.locks.fail_open') === true
                ? new ResilientSeatLockService($redis, $app->make(LoggerInterface::class))
                : $redis;
        });
    }

    public function boot(): void
    {
        Date::use(CarbonImmutable::class);
        Model::shouldBeStrict(! $this->app->isProduction());
        DB::prohibitDestructiveCommands($this->app->isProduction());

        $this->configureRateLimiting();
    }

    private function configureRateLimiting(): void
    {
        $byUserOrIp = function (Request $request): string {
            $id = $request->user()?->getAuthIdentifier();

            return is_scalar($id) ? 'user:'.$id : 'ip:'.$request->ip();
        };

        $limit = function (string $key, int $default): int {
            $value = config("seatlock.rate_limits.{$key}");

            return is_numeric($value) ? (int) $value : $default;
        };

        RateLimiter::for('api', fn (Request $request): Limit => Limit::perMinute($limit('api', 120))->by($byUserOrIp($request)));

        RateLimiter::for('auth', fn (Request $request): Limit => Limit::perMinute(10)->by('ip:'.((string) $request->ip())));

        // Os endpoints de lock são o alvo preferido de abuso (travar o ônibus
        // inteiro): limite por usuário e, separadamente, por IP.
        RateLimiter::for('seat-locks', fn (Request $request): array => [
            Limit::perMinute($limit('locks_per_user', 30))->by($byUserOrIp($request)),
            Limit::perMinute($limit('locks_per_ip', 60))->by('ip:'.((string) $request->ip())),
        ]);

        RateLimiter::for('orders', fn (Request $request): Limit => Limit::perMinute($limit('orders', 20))->by($byUserOrIp($request)));

        RateLimiter::for('webhooks', fn (Request $request): Limit => Limit::perMinute(600)->by('ip:'.((string) $request->ip())));
    }
}
