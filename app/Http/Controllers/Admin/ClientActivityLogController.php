<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ClientActivityLog;
use App\Models\User;
use Illuminate\Http\Request;

/** CRM → Client Activity: what clients viewed on the website, and when. */
class ClientActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $q = ClientActivityLog::query()->with('client:id,name,photo,email')->latest('id');

        if ($client = $request->query('client')) {
            $q->where('client_id', $client);
        }
        match ($request->query('date_range')) {
            'today' => $q->whereDate('created_at', today()),
            'week' => $q->whereBetween('created_at', [now()->startOfWeek(), now()->endOfWeek()]),
            'month' => $q->whereBetween('created_at', [now()->startOfMonth(), now()->endOfMonth()]),
            default => null,
        };
        if ($from = $request->query('from')) {
            $q->whereDate('created_at', '>=', $from);
        }
        if ($to = $request->query('to')) {
            $q->whereDate('created_at', '<=', $to);
        }

        return view('admin.client-activity.index', [
            'logs' => $q->paginate(30)->withQueryString(),
            'clients' => User::clients()->whereIn('id', ClientActivityLog::distinct()->pluck('client_id')->filter())->orderBy('name')->get(['id', 'name']),
        ]);
    }
}
