<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ContactMessageController extends Controller
{
    public function index(Request $request)
    {
        $q = ContactMessage::latest();
        if (($status = $request->query('status')) && array_key_exists($status, ContactMessage::STATUSES)) {
            $q->where('status', $status);
        }
        if ($search = trim((string) $request->query('search'))) {
            $q->where(fn ($w) => $w->where('name', 'like', "%{$search}%")
                ->orWhere('email', 'like', "%{$search}%")
                ->orWhere('phone', 'like', "%{$search}%"));
        }
        $messages = $q->paginate(20)->withQueryString();

        // Mark the current page's messages as read (keeps the sidebar "new" badge accurate).
        ContactMessage::whereIn('id', collect($messages->items())->pluck('id'))
            ->where('is_read', false)->update(['is_read' => true]);

        return view('admin.messages.index', [
            'messages' => $messages,
            'status' => $status ?? '',
            'search' => $search ?? '',
        ]);
    }

    public function show(ContactMessage $message)
    {
        $message->update(['is_read' => true]);

        return view('admin.messages.show', compact('message'));
    }

    public function updateStatus(Request $request, ContactMessage $message)
    {
        $data = $request->validate(['status' => ['required', Rule::in(array_keys(ContactMessage::STATUSES))]]);
        $message->update(['status' => $data['status']]);

        return back()->with('status', 'Status updated.');
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        // Always land on the list (the show page for this record is gone now).
        return redirect()->route('admin.messages.index')->with('status', 'Enquiry deleted.');
    }
}
