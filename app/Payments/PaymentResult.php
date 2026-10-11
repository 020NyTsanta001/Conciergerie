<?php

namespace App\Payments;

final class PaymentResult
{
    public function __construct(public readonly string $reference, public readonly string $status = 'paid') {}
}