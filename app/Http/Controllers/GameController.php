<?php

namespace App\Http\Controllers;

use App\Models\GameSession;
use App\Models\User;
use App\Services\WalletService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class GameController extends Controller
{
    /** POST /games/pacman/start : appelé au premier mouvement du joueur, le chrono démarre ici */
    public function start(Request $request)
    {
        $user = $request->user();
        $max = (int) config('money.pacman.max_paid_games');

        $session = DB::transaction(function () use ($user, $max) {
            User::whereKey($user->id)->lockForUpdate()->first();   // évite deux départs simultanés
            $played = GameSession::where('user_id', $user->id)->where('game', 'pacman')->count();

            return GameSession::create([
                'user_id' => $user->id,
                'game' => 'pacman',
                'started_at' => now(),
                'paid' => $played < $max,
            ]);
        });

        return ['id' => $session->id, 'paid' => $session->paid];
    }

    /** POST /games/pacman/{session}/finish : game over, le serveur calcule la durée et le gain */
    public function finish(Request $request, GameSession $session, WalletService $wallet)
    {
        $user = $request->user();
        abort_unless((int) $session->user_id === (int) $user->id, 403);

        $cfg = config('money.pacman');

        return DB::transaction(function () use ($session, $user, $wallet, $cfg) {
            $s = GameSession::whereKey($session->id)->lockForUpdate()->firstOrFail();
            abort_if($s->finished_at !== null, 422, 'Partie déjà terminée.');

            $seconds = max(0, (int) $s->started_at->diffInSeconds(now()));   // heure du serveur
            $steps = min($cfg['max_steps'], intdiv($seconds, $cfg['step_seconds']));
            $amount = $s->paid ? $steps * $cfg['step_amount'] : 0;

            $s->update(['finished_at' => now(), 'seconds' => $seconds, 'amount' => $amount]);
            if ($amount > 0) {
                $wallet->creditGame($user, $amount, $s->id);
            }

            return [
                'seconds' => $seconds,
                'amount' => $amount,
                'paid' => $s->paid,
                'balance' => (int) $user->balance,
            ];
        });
    }
}