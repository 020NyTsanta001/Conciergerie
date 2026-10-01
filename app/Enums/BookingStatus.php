<?php

namespace App\Enums;

enum BookingStatus: string
{
    case Submitted = 'submitted';
    case PendingHost = 'pending_host';
    case Validated = 'validated';
    case DepositPaid = 'deposit_paid';
    case BalancePaid = 'balance_paid';
    case InProgress = 'in_progress';
    case Closed = 'closed';
    case Cancelled = 'cancelled';

    /** Diagramme d'états-transitions : transitions autorisées depuis chaque état. */
    public function next(): array
    {
        return match ($this) {
            self::Submitted => [self::PendingHost, self::Cancelled],
            self::PendingHost => [self::Validated, self::Cancelled],
            self::Validated => [self::DepositPaid, self::Cancelled],
            self::DepositPaid => [self::BalancePaid, self::Cancelled],
            self::BalancePaid => [self::InProgress],
            self::InProgress => [self::Closed],
            default => [],
        };
    }

    public function canGoTo(self $to): bool
    {
        return in_array($to, $this->next(), true);
    }
}
