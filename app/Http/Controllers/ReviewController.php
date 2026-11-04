<?php

namespace App\Http\Controllers;

use App\Enums\BookingStatus;
use App\Models\ActivityLog;
use App\Models\Booking;
use App\Models\Review;
use App\Models\Villa;
use App\Notifications\InfoNotification;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class ReviewController extends Controller
{
    private function rules(): array
    {
        return [
            'rating' => 'required|integer|between:1,5',
            'comment' => 'nullable|string|max:300', // « required » pour rendre le message obligatoire
        ];
    }

    private function row(Review $r): array
    {
        return [
            'id' => (int) $r->id,
            'user_id' => (int) $r->user_id,
            'author' => Str::words($r->user->name, 2, ''),
            'rating' => $r->rating,
            'comment' => $r->comment,
            'created_at' => $r->created_at,
            'updated_at' => $r->updated_at,
        ];
    }

    /** GET /properties/{villa}/reviews : public */
    public function index(Villa $villa)
    {
        abort_unless($villa->status === 'approved', 404);

        return [
            'average' => round((float) $villa->reviews()->avg('rating'), 1),
            'count' => $villa->reviews()->count(),
            'items' => $villa->reviews()->with('user:id,name')->latest()->get()->map(fn ($r) => $this->row($r))->values(),
        ];
    }

    /** POST /bookings/{booking}/review : le voyageur, une fois le séjour clôturé, une seule fois par séjour */
    public function store(Request $request, Booking $booking)
    {
        abort_unless((int) $booking->user_id === (int) $request->user()->id, 403, "Seul le voyageur de ce séjour peut donner son avis.");

        if ($booking->status !== BookingStatus::Closed) {
            throw ValidationException::withMessages(['review' => 'Vous pourrez donner votre avis une fois le séjour clôturé.']);
        }
        if (Review::where('booking_id', $booking->id)->exists()) {
            throw ValidationException::withMessages(['review' => 'Vous avez déjà donné votre avis sur ce séjour.']);
        }

        $data = $request->validate($this->rules());

        $review = Review::create($data + [
            'booking_id' => $booking->id, 'villa_id' => $booking->villa_id, 'user_id' => $booking->user_id,
        ]);
        $review->load('user:id,name', 'villa:id,title');

        ActivityLog::record($request->user(), 'review_created', "a publié un avis ({$review->rating}/5) sur la villa « {$review->villa->title} »");

        return response()->json($this->row($review), 201);
    }

    /** PUT /reviews/{review} : l'auteur uniquement */
    public function update(Request $request, Review $review)
    {
        abort_unless((int) $review->user_id === (int) $request->user()->id, 403, "Seul l'auteur peut modifier son avis.");

        $review->update($request->validate($this->rules()));

        return $this->row($review->load('user:id,name'));
    }

    /** DELETE /reviews/{review} : l'auteur, ou un admin (l'auteur est alors notifié) */
    public function destroy(Request $request, Review $review)
    {
        $u = $request->user();
        $isAuthor = (int) $review->user_id === (int) $u->id;
        abort_unless($isAuthor || $u->role === 'admin', 403, "Seuls l'auteur ou un concierge peuvent supprimer cet avis.");

        $review->load('villa:id,title', 'user');

        if (! $isAuthor) {
            $review->user->notify(new InfoNotification("Votre avis sur la villa « {$review->villa->title} » a été supprimé par un concierge."));
        }
        ActivityLog::record($u, 'review_deleted', $isAuthor
            ? "a supprimé son avis sur la villa « {$review->villa->title} »"
            : "a supprimé l'avis de {$review->user->name} sur la villa « {$review->villa->title} »");

        $review->delete();

        return response()->noContent();
    }
}