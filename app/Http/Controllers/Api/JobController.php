<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\JobOpening;

class JobController extends Controller
{
    /** Published openings for the public careers page (newest first). Drafts never leave the admin. */
    public function index()
    {
        $jobs = JobOpening::published()
            ->orderByDesc('published_at')
            ->orderByDesc('id')
            ->get(['id', 'title', 'slug', 'department', 'type', 'location', 'description', 'apply_url']);

        return response()->json(['data' => $jobs]);
    }
}
