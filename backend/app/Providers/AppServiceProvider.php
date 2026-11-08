<?php

namespace App\Providers;

use App\Domain\Booking\Payment\PaymentCancellationGateway;
use App\Domain\Booking\Payment\UnavailablePaymentCancellationGateway;
use App\Models\User;
use App\Support\PerformanceQueryRecorder;
use Carbon\CarbonImmutable;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Contracts\Auth\CanResetPassword;
use Illuminate\Support\Facades\Date;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentCancellationGateway::class, UnavailablePaymentCancellationGateway::class);
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        Gate::define('viewApiDocs', fn (User $user): bool => $user->status === 'ACTIVE' && $user->hasRole('ADMIN'));

        if (config('performance.enabled') && app()->environment('performance')) {
            DB::listen(PerformanceQueryRecorder::record(...));
        }

        Date::use(CarbonImmutable::class);

        DB::prohibitDestructiveCommands(app()->isProduction());

        ResetPassword::createUrlUsing(fn (CanResetPassword $notifiable, string $token): string => rtrim(config('app.frontend_url'), '/')
            .'/reset-password?'.http_build_query([
                'token' => $token,
                'email' => $notifiable->getEmailForPasswordReset(),
            ]));

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
