<?php

namespace App\Providers;

use App\Contracts\CommentRepositoryInterface;
use App\Contracts\TicketRepositoryInterface;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use App\Policies\AttachmentPolicy;
use App\Policies\TicketPolicy;
use App\Repositories\CommentRepository;
use App\Repositories\TicketRepository;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;
use Illuminate\Validation\Rules\Password;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(TicketRepositoryInterface::class, TicketRepository::class);
        $this->app->bind(CommentRepositoryInterface::class, CommentRepository::class);
    }

    public function boot(): void
    {
        // Surface N+1 queries and silently dropped attributes during development.
        Model::preventLazyLoading(! $this->app->isProduction());
        Model::preventSilentlyDiscardingAttributes(! $this->app->isProduction());

        Password::defaults(fn () => Password::min(8)->letters()->numbers());

        $this->configureAuthorization();
        $this->configureRateLimiting();
    }

    private function configureAuthorization(): void
    {
        Gate::policy(Ticket::class, TicketPolicy::class);
        Gate::policy(Attachment::class, AttachmentPolicy::class);

        Gate::define('admin', fn (User $user) => $user->isAdmin());
        Gate::define('staff', fn (User $user) => $user->isStaff());
    }

    private function configureRateLimiting(): void
    {
        $limits = config('servicedesk.rate_limits');

        RateLimiter::for('api', fn (Request $request) => Limit::perMinute($limits['api'])
            ->by($request->user()?->id ?: $request->ip()));

        // Keyed by email + IP so one attacker cannot lock out every account,
        // and a single account cannot be brute forced from one address.
        RateLimiter::for('auth', fn (Request $request) => [
            Limit::perMinute($limits['auth'])->by(strtolower((string) $request->input('email')).'|'.$request->ip()),
            Limit::perMinute($limits['auth'] * 4)->by($request->ip()),
        ]);

        RateLimiter::for('password-reset', fn (Request $request) => Limit::perMinute($limits['password_reset'])
            ->by(strtolower((string) $request->input('email')).'|'.$request->ip()));
    }
}
