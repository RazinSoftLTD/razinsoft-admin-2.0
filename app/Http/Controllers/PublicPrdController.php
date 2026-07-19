<?php

namespace App\Http\Controllers;

use App\Models\Project;
use Illuminate\Http\Request;

/**
 * Client-facing PRD page. Reached only through the project's share token —
 * no login. Clients may submit; they cannot delete, review, or see other projects.
 */
class PublicPrdController extends Controller
{
    private function project(string $token): Project
    {
        $project = Project::where('prd_share_token', $token)->first();
        abort_if(! $project || ! $project->needs_requirements || empty($project->prdSectionKeys()), 404);

        return $project;
    }

    public function show(string $token)
    {
        $project = $this->project($token);

        return view('prd.public', [
            'project' => $project,
            'token' => $token,
            'sections' => $project->prdSectionKeys(),
            'items' => $project->prdItems()->get()->groupBy('section'),
        ]);
    }

    public function store(Request $request, string $token)
    {
        $project = $this->project($token);

        $data = $request->validate([
            'section' => ['required', 'string', 'in:'.implode(',', $project->prdSectionKeys())],
            'submitted_by_name' => ['nullable', 'string', 'max:80'],
            'note' => ['nullable', 'string', 'max:5000'],
            'files' => ['nullable', 'array'],
            'files.*' => ['file', 'max:20480'],
        ]);

        $uploads = $request->file('files', []);
        if (! $uploads && blank($data['note'] ?? null)) {
            return back()->withErrors(['files' => 'Attach a file or write a note.']);
        }

        $who = $data['submitted_by_name'] ?? $project->client?->name;

        foreach ($uploads as $upload) {
            $project->prdItems()->create([
                'section' => $data['section'],
                'name' => $upload->getClientOriginalName(),
                'path' => $upload->store('projects/'.$project->id.'/prd', 'public'),
                'mime' => $upload->getClientMimeType(),
                'size' => $upload->getSize(),
                'submitted_by_name' => $who,
            ]);
        }
        if (filled($data['note'] ?? null)) {
            $project->prdItems()->create([
                'section' => $data['section'],
                'note' => $data['note'],
                'submitted_by_name' => $who,
            ]);
        }

        $label = Project::PRD_SECTIONS[$data['section']][0];
        $project->log('file', 'Client submitted PRD — '.$label.'.');

        return back()->with('status', 'Thanks! Your information has been submitted for review.');
    }
}
