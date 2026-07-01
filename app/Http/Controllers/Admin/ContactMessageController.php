<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;

class ContactMessageController extends Controller
{
    public function index()
    {
        $messages = ContactMessage::latest()->paginate(20);

        // Mark the current page's messages as read.
        ContactMessage::whereIn('id', collect($messages->items())->pluck('id'))->where('is_read', false)->update(['is_read' => true]);

        return view('admin.messages.index', compact('messages'));
    }

    public function destroy(ContactMessage $message)
    {
        $message->delete();

        return back()->with('status', 'Message deleted.');
    }
}
