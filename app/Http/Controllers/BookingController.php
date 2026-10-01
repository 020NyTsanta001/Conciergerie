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

    public function index(Request $request)
    {
        $u = $request->user();
        $q = Booking::with(['villa.images', 'user', 'services'])->latest();

        if ($u->role === 'host') {
            $q->whereHas('villa', fn ($v) => $v->where('owner_id', $u->id));
        } elseif ($u->role !== 'admin') {
            $q->where('user_id', $u->id);
        }

        return BookingResource::collection($q->get());
    }

    public function quote(Request $request, PricingService $pricing)
    {
        $data = $request->validate($this->rules());
        $villa = Villa::approved()->with('seasons')->findOrFail($data['villa_id']);

        return $pricing->quote($villa, Carbon::parse($data['check_in']), Carbon::parse($data['check_out']), $data['services'] ?? []);
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->rules());

        return (new BookingResource($this->bookings->create($request->user(), $data)))
            ->response()->setStatusCode(201);
    }

    public function status(Request $request, Booking $booking)
    {
        $action = $request->validate(['action' => 'required|in:validate,start,close,cancel'])['action'];
        $booking->load('villa');

        return new BookingResource($this->bookings->transition($request->user(), $booking, $action)->load(['villa.images', 'user', 'services']));
    }

    public function pay(Request $request, Booking $booking)
    {
        $type = $request->validate(['type' => 'required|in:deposit,balance'])['type'];
        $booking->load('villa');
        $result = $this->bookings->pay($request->user(), $booking, $type);

        return [
            'reference' => $result['reference'],
            'amount' => $result['amount'],
            'booking' => new BookingResource($result['booking']->load(['villa.images', 'user', 'services'])),
        ];
    }

    public function notifications(Request $request)
    {
        return $request->user()->notifications()->latest()->limit(30)->get()
            ->map(fn ($n) => ['id' => $n->id, 'message' => $n->data['message'] ?? '', 'booking_id' => $n->data['booking_id'] ?? null, 'read' => (bool) $n->read_at, 'created_at' => $n->created_at]);
    }
}
