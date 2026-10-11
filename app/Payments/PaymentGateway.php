<?php

namespace App\Payments;

use App\Models\User;
use App\Payments\PaymentResult;

interface PaymentGateway
{
    /**
     * Encaisse $amountAr (Ariary, entier) auprès de $payer.
     * Doit lever une ValidationException si le paiement échoue.
     */
    public function charge(User $payer, int $amountAr, string $description, ?int $bookingId = null): PaymentResult;
}