<?php

namespace App\Payments;

use App\Models\User;
use App\Services\WalletService;
use Illuminate\Support\Str;

class WalletGateway implements PaymentGateway
{
    public function __construct(private WalletService $wallet) {}

    public function charge(User $payer, int $amountAr, string $description, ?int $bookingId = null): PaymentResult
    {
        $this->wallet->debit($payer, $amountAr, 'payment', $description, $bookingId,
            'Solde insuffisant : récupérez des bonus ou choisissez le paiement sur place.');

        return new PaymentResult('WAL-'.Str::upper(Str::random(8)));
    }

    public function refund(User $payer, int $amountAr, string $description, ?int $bookingId = null): PaymentResult
    {
        $this->wallet->credit($payer, $amountAr, 'refund', $description, $bookingId);

        return new PaymentResult('REF-'.Str::upper(Str::random(8)));
    }
}