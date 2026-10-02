<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use Illuminate\Http\Request;

class AdminActivityController extends Controller
{
    /** GET /admin/activity?role=client|host|admin&user_id=5&page=2 */
    public function index(Request $request)
    {
        return ActivityLog::query()
            ->when($request->query('role'), fn ($q, $r) => $q->where('actor_role', $r))
            ->when($request->query('user_id'), fn ($q, $id) => $q->where('user_id', $id))
            ->orderByDesc('id')
            ->paginate(30);
    }
}