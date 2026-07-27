<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use Illuminate\Http\Request;

class ContactController extends Controller
{
    public function store(Request $request)
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'company' => ['nullable', 'string', 'max:255'],
            'service' => ['nullable', 'string', 'max:255'],
            'budget' => ['nullable', 'string', 'max:255'],
            'message' => ['required', 'string', 'max:5000'],
        ]);

        $message = ContactMessage::create($data);

        $this->reportLead($request, $message->id, $data, 'Contact form');

        return response()->json(['message' => "Thanks! We'll get back to you within 24 hours."], 201);
    }

    /**
     * Tell Meta a lead came in.
     *
     * The request is passed through so the visitor's pixel cookies and IP travel with the event —
     * they are what let Meta tie it back to the ad that produced it.
     */
    private function reportLead(Request $request, int $id, array $data, string $source): void
    {
        try {
            [$first, $last] = array_pad(explode(' ', trim((string) ($data['name'] ?? '')), 2), 2, null);

            \App\Services\Meta\ConversionsApi::make()->send('Lead', 'contact-'.$id, [
                'content_name' => $source,
                'source_url' => rtrim((string) config('brand.website'), '/').'/contact',
            ], [
                'email' => $data['email'] ?? null,
                'phone' => $data['phone'] ?? null,
                'first_name' => $first,
                'last_name' => $last,
            ], $request);
        } catch (\Throwable $e) {
            // A tracking failure must not cost us the lead.
            report($e);
        }
    }
}
