<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Subscriber;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

class SubscriberController extends Controller
{
    public function index(Request $request)
    {
        $q = Subscriber::query()->latest('id');

        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('email', 'like', "%{$search}%")->orWhere('name', 'like', "%{$search}%"));
        }

        if ($request->query('export') === 'csv') {
            return $this->exportCsv($q->get());
        }

        return view('admin.subscribers.index', [
            'subscribers' => $q->paginate(20)->withQueryString(),
            'total' => Subscriber::count(),
            'active' => Subscriber::where('is_active', true)->count(),
        ]);
    }

    /** Manually add a subscriber. */
    public function store(Request $request)
    {
        $data = $request->validate([
            'email' => ['required', 'email', 'max:255', Rule::unique('subscribers', 'email')],
            'name' => ['nullable', 'string', 'max:255'],
        ]);
        $data['email'] = strtolower($data['email']);
        $data['source'] = 'manual';

        Subscriber::create($data);

        return back()->with('status', 'Subscriber added.');
    }

    /** Toggle active/unsubscribed. */
    public function update(Request $request, Subscriber $subscriber)
    {
        $subscriber->update(['is_active' => $request->boolean('is_active')]);

        return back()->with('status', 'Subscriber updated.');
    }

    public function destroy(Subscriber $subscriber)
    {
        $subscriber->delete();

        return back()->with('status', 'Subscriber removed.');
    }

    private function exportCsv($subscribers): StreamedResponse
    {
        return response()->streamDownload(function () use ($subscribers) {
            $out = fopen('php://output', 'w');
            fputcsv($out, ['Email', 'Name', 'Source', 'Article', 'Active', 'Subscribed At']);
            foreach ($subscribers as $s) {
                fputcsv($out, [$s->email, $s->name, $s->source, $s->article, $s->is_active ? 'Yes' : 'No', $s->created_at->format('Y-m-d H:i')]);
            }
            fclose($out);
        }, 'subscribers-'.now()->format('Y-m-d').'.csv', ['Content-Type' => 'text/csv']);
    }
}
