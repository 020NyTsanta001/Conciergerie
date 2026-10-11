<?php

namespace App\Http\Controllers;

use App\Models\WalletTransaction;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class WalletController extends Controller
{
    /** GET /wallet : solde, bonus disponibles/récupérés, derniers mouvements */
    public function show(Request $request)
    {
        $u = $request->user();

        return [
            'balance' => (int) $u->balance,
            'rewards' => config('money.rewards'),
            'claimed' => WalletTransaction::where('user_id', $u->id)->whereNotNull('reward_key')->pluck('reward_key'),
            'transactions' => WalletTransaction::where('user_id', $u->id)->orderByDesc('id')->limit(20)
                ->get(['id', 'type', 'amount', 'balance_after', 'description', 'created_at']),
        ];
    }

    /** POST /wallet/claim {key} : bonus de visite, une fois par bouton et par compte */
    public function claim(Request $request, WalletService $wallet)
    {
        $key = $request->validate([
            'key' => ['required', Rule::in(array_keys(config('money.rewards')))],
        ])['key'];

        $tx = $wallet->claimReward($request->user(), $key);

        return ['key' => $key, 'amount' => $tx->amount, 'balance' => $tx->balance_after];
    }
}