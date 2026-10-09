<?php

namespace App\Http\Controllers;

use App\Models\Booking;
use App\Models\Payout;
use Illuminate\Http\Request;

class PayoutController extends Controller
{
    private function row(Payout $p): array
    {
        return [
            'id' => $p->id,
            'booking_id' => $p->booking_id,
            'villa_title' => $p->booking?->villa?->title,
            'host_name' => $p->host?->name,
            'gross' => $p->gross,
            'commission' => $p->commission,
            'amount' => $p->amount,
            'created_at' => $p->created_at,
        ];
    }

    /** GET /host/payouts : ce que l'hôte a reçu (acomptes + 85 % des séjours payés en intégralité) */
    public function hostIndex(Request $request)
    {
        $hostId = $request->user()->id;

        $settled = Payout::where('host_id', $hostId)
            ->with(['booking.villa:id,title', 'host:id,name'])->latest()->get();

        // réservations où seul l'acompte a été payé : il est déjà chez l'hôte
        $depositsOnly = (float) Booking::where('status', 'deposit_paid')
            ->whereHas('villa', fn ($v) => $v->where('owner_id', $hostId))
            ->sum('deposit_amount');

        return [
            'received' => (float) $settled->sum('amount') + $depositsOnly,
            'items' => $settled->map(fn ($p) => $this->row($p))->values(),
        ];
    }

    /** GET /admin/payouts : les commissions de la plateforme */
    public function adminIndex()
    {
        $rows = Payout::with(['booking.villa:id,title', 'host:id,name'])->latest()->get();

        return [
            'commission_total' => (float) $rows->sum('commission'),
            'items' => $rows->map(fn ($p) => $this->row($p))->values(),
        ];
    }
}