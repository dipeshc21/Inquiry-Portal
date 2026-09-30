<?php

namespace App\Providers;

use App\Http\Resources\ApiResponse;
use App\Models\Inquiry;
use App\Models\Note;
use App\Models\Reminder;
use App\Models\User;
use App\Observers\InquiryObserver;
use App\Policies\InquiryPolicy;
use App\Policies\NotePolicy;
use App\Policies\ReminderPolicy;
use App\Policies\UserPolicy;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        Model::preventLazyLoading(! $this->app->isProduction());

        Gate::policy(Inquiry::class, InquiryPolicy::class);
        Gate::policy(User::class, UserPolicy::class);
        Gate::policy(Note::class, NotePolicy::class);
        Gate::policy(Reminder::class, ReminderPolicy::class);

        Gate::define(
            'view-dashboard',
            fn (User $user): bool => $user->is_active
                && ($user->canManageInquiries() || $user->isAgent())
        );

        Gate::define(
            'view-activity-logs',
            fn (User $user): bool => $user->is_active && $user->isAdmin()
        );

        Gate::define(
            'manage-settings',
            fn (User $user): bool => $user->is_active && $user->isAdmin()
        );

        Gate::define(
            'manage-teams',
            fn (User $user): bool => $user->is_active && $user->isAdmin()
        );

        Inquiry::observe(InquiryObserver::class);

        RateLimiter::for('public-inquiries', function (Request $request) {
            return Limit::perMinute(5)
                ->by('public-inquiries:'.$request->ip())
                ->response(function (
                    Request $request,
                    array $headers
                ) {
                    return ApiResponse::failure(
                        'You have submitted too many inquiries. '
                            .'Please try again in a minute.'
                    )
                        ->response()
                        ->setStatusCode(429)
                        ->withHeaders($headers);
                });
        });

        RateLimiter::for('login', function (Request $request): array {
            $email = $request->input('email');
            $email = is_string($email)
                ? mb_strtolower(trim($email))
                : '';

            $response = function (Request $request, array $headers) {
                return ApiResponse::failure(
                    'Too many login attempts. Please try again shortly.'
                )
                    ->response()
                    ->setStatusCode(429)
                    ->withHeaders($headers);
            };

            return [
                Limit::perMinute(20)
                    ->by('login-ip:'.$request->ip())
                    ->response($response),

                Limit::perMinute(5)
                    ->by(
                        'login-account:'.$request->ip().':'
                            .hash('sha256', $email)
                    )
                    ->response($response),
            ];
        });

        // Laravel 11 discovers typed handle() methods in app/Listeners.
        // Do not also register those listeners here, which could cause
        // duplicate notifications.
    }
}
