<?php

namespace App\Providers;
use App\Support\ActivityTracker;
use App\Payments\PaymentGateway;
use App\Payments\WalletGateway;

use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Register any application services.
     */
    public function register(): void
    {
        $this->app->bind(PaymentGateway::class, fn ($app) => match (config('money.payment_driver')) {
            'wallet' => $app->make(WalletGateway::class),
            // 'mvola' => $app->make(MvolaGateway::class),   ← à ajouter avec un vrai prestataire
            default => throw new \RuntimeException('Moyen de paiement inconnu : '.config('money.payment_driver')),
        });
    }

    /**
     * Bootstrap any application services.
     */
    public function boot(): void
    {
        ActivityTracker::register();

        // Connexion : 5 essais par minute pour un même e-mail depuis une même adresse IP,
        // et 20 par minute au total pour une adresse IP (anti « force brute »).
        RateLimiter::for('login', function (Request $request) {
            $tooMany = fn () => response()->json([
                'message' => 'Trop de tentatives de connexion. Réessayez dans une minute.',
            ], 429);

            return [
                Limit::perMinute(5)->by(mb_strtolower((string) $request->input('email')).'|'.$request->ip())->response($tooMany),
                Limit::perMinute(20)->by($request->ip())->response($tooMany),
            ];
        });
    }
}
