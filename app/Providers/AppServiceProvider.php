<?php

namespace App\Providers;
use App\Support\ActivityTracker;
use App\Payments\PaymentGateway;
use App\Payments\WalletGateway;

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
    }
}
