<?php

namespace App\Providers;

use App\Models\PusProfile;
use App\Policies\PusProfilePolicy;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

use Illuminate\Support\Facades\URL;

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
        if (
            app()->isProduction()
            || str_starts_with((string) config('app.url'), 'https://')
            || request()->header('X-Forwarded-Proto') === 'https'
        ) {
            URL::forceScheme('https');
        }

        Gate::policy(PusProfile::class, PusProfilePolicy::class);

        $this->configureSecurityLogging();

        $this->configureDefaults();
    }

    /**
     * Configure structured security logging for authentication and authorization events.
     */
    protected function configureSecurityLogging(): void
    {
        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Failed::class, function (\Illuminate\Auth\Events\Failed $event): void {
            \Illuminate\Support\Facades\Log::warning('security.auth_failed', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
                'guard' => $event->guard,
                'has_user' => $event->user !== null,
                'user_id' => $event->user?->id,
                'masked_identifier' => isset($event->credentials['email']) ? substr((string) $event->credentials['email'], 0, 3).'***' : null,
            ]);
        });

        \Illuminate\Support\Facades\Event::listen(\Illuminate\Auth\Events\Lockout::class, function (\Illuminate\Auth\Events\Lockout $event): void {
            \Illuminate\Support\Facades\Log::alert('security.auth_lockout', [
                'ip' => request()->ip(),
                'user_agent' => request()->userAgent(),
            ]);
        });
    }

    /**
     * Configure default behaviors for production-ready applications.
     */
    protected function configureDefaults(): void
    {
        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(
            app()->isProduction(),
        );

        Password::defaults(fn (): ?Password => app()->isProduction()
            ? Password::min(12)
                ->mixedCase()
                ->letters()
                ->numbers()
                ->symbols()
                ->uncompromised()
            : null,
        );
    }
}
