<?php

namespace App\Services;

use App\Models\User;
use App\Models\WalletTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Seul endroit où le solde d'un compte est modifié. */
class WalletService
{
    public function credit(User $user, int $amount, string $type, string $description, ?int $bookingId = null): ?WalletTransaction
    {
        return $amount > 0 ? $this->apply($user, $amount, $type, $description, $bookingId) : null;
    }

    public function debit(User $user, int $amount, string $type, string $description, ?int $bookingId = null, string $insufficient = 'Solde insuffisant.'): ?WalletTransaction
    {
        return $amount > 0 ? $this->apply($user, -$amount, $type, $description, $bookingId, null, $insufficient) : null;
    }

    /** Bonus de visite : une seule fois par clé et par compte. */
    public function claimReward(User $user, string $key): WalletTransaction
    {
        $amount = (int) config("money.rewards.{$key}");
        if ($amount <= 0) {
            throw ValidationException::withMessages(['key' => 'Bonus inconnu.']);
        }

        return $this->apply($user, $amount, 'reward', "Bonus de visite : {$key}", null, $key);
    }

    /** Gain d'une partie : clé unique par partie, donc non bloqué par « un bonus par bouton ». */
    public function creditGame(User $user, int $amount, int $sessionId): WalletTransaction
    {
        return $this->apply($user, $amount, 'reward', 'Gain Pac-Man', null, "pacman:{$sessionId}");
    }

    private function apply(
        User $user, int $amount, string $type, string $description,
        ?int $bookingId = null, ?string $rewardKey = null, string $insufficient = 'Solde insuffisant.'
    ): WalletTransaction {
        return DB::transaction(function () use ($user, $amount, $type, $description, $bookingId, $rewardKey, $insufficient) {
            $locked = User::whereKey($user->id)->lockForUpdate()->firstOrFail(); // évite deux écritures simultanées

            if ($rewardKey && WalletTransaction::where('user_id', $locked->id)->where('reward_key', $rewardKey)->exists()) {
                throw ValidationException::withMessages(['key' => 'Bonus déjà récupéré.']);
            }

            $after = (int) $locked->balance + $amount;
            if ($after < 0) {
                throw ValidationException::withMessages(['balance' => $insufficient]);
            }

            $locked->forceFill(['balance' => $after])->save();
            $user->setAttribute('balance', $after);       // garde l'objet de l'appelant à jour
            $user->syncOriginalAttribute('balance');

            return WalletTransaction::create([
                'user_id' => $locked->id,
                'type' => $type,
                'amount' => $amount,
                'balance_after' => $after,
                'description' => $description,
                'booking_id' => $bookingId,
                'reward_key' => $rewardKey,
            ]);
        });
    }
}