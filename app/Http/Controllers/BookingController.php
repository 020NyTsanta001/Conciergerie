<?php

namespace App\Http\Controllers;

use App\Http\Resources\BookingResource;
use App\Models\Booking;
use App\Models\Villa;
use App\Services\BookingService;
use App\Services\PricingService;
use Carbon\Carbon;
use Illuminate\Http\Request;

class BookingController extends Controller
{
    public function __construct(private BookingService $bookings) {}

    private function rules(): array
    {
        return [
            'villa_id' => 'required|exists:villas,id',
            'check_in' => 'required|date|after_or_equal:today',
            'check_out' => 'required|date|after:check_in',
            'guests' => 'required|integer|min:1',
            'services' => 'sometimes|array',
            'services.*.id' => 'required|exists:services,id',
            'services.*.quantity' => 'sometimes|integer|min:1|max:30',
        ];
    }

    /** Seul un compte voyageur réserve : l'admin ET le propriétaire doivent se connecter avec un compte voyageur. */
    private function ensureTraveler(Request $request): void
    {
        $role = $request->user()->role;

        abort_if($role === 'admin', 403, 'Le concierge ne réserve pas : connectez-vous avec un compte voyageur.');
        abort_if($role === 'host', 403, 'Un propriétaire ne réserve pas : connectez-vous avec un compte voyageur.');
    }

    public function index(Request $request)
    {
        $u = $request->user();
        $q = Booking::with(['villa.images', 'user', 'services', 'review'])->latest();

        if ($u->role === 'host') {
            $q->whereHas('villa', fn ($v) => $v->where('owner_id', $u->id));
        } elseif ($u->role !== 'admin') {
            $q->where('user_id', $u->id);
        }

        return BookingResource::collection($q->get());
    }

    public function quote(Request $request, PricingService $pricing)
    {
        $this->ensureTraveler($request);
        $data = $request->validate($this->rules());
        $villa = Villa::approved()->findOrFail($data['villa_id']);

        return $pricing->quote($villa, Carbon::parse($data['check_in']), Carbon::parse($data['check_out']), $data['services'] ?? []);
    }

    public function store(Request $request)
    {
        $this->ensureTraveler($request);
        $data = $request->validate($this->rules());

        return (new BookingResource($this->bookings->create($request->user(), $data)))
            ->response()->setStatusCode(201);
    }

    public function status(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'action' => 'required|in:validate,start,close,cancel,confirm_onsite,refund,request_refund,decline_refund',
            // la cause est obligatoire pour demander un remboursement (voyageur) et pour le refuser (hôte)
            'reason' => 'required_if:action,request_refund,decline_refund|nullable|string|min:5|max:500',
        ], [
            'reason.required_if' => 'Écrivez la cause avant de continuer.',
            'reason.min' => 'La cause est trop courte (5 caractères minimum).',
        ]);
        $booking->load('villa');

        return new BookingResource(
            $this->bookings->transition($request->user(), $booking, $data['action'], $data['reason'] ?? null)
                ->load(['villa.images', 'user', 'services'])
        );
    }

    public function pay(Request $request, Booking $booking)
    {
        $data = $request->validate([
            'type' => 'required|in:deposit,balance',
            'method' => 'sometimes|in:wallet,on_site', // « on_site » : seulement pour le solde
        ]);
        $booking->load('villa');
        $result = $this->bookings->pay($request->user(), $booking, $data['type'], $data['method'] ?? 'wallet');

        return [
            'reference' => $result['reference'],
            'amount' => $result['amount'],
            'booking' => new BookingResource($result['booking']->load(['villa.images', 'user', 'services'])),
        ];
    }

    public function notifications(Request $request)
    {
        return $request->user()->notifications()->latest()->limit(30)->get()
            ->map(fn ($n) => ['id' => $n->id, 'message' => $n->data['message'] ?? '', 'booking_id' => $n->data['booking_id'] ?? null, 'area' => $n->data['area'] ?? null, 'read' => (bool) $n->read_at, 'created_at' => $n->created_at]);
    }
}
