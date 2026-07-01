<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Author;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

class AuthorController extends Controller
{
    public function index()
    {
        $authors = Author::withCount('articles')->orderBy('name')->get();

        return view('admin.authors.index', compact('authors'));
    }

    public function store(Request $request)
    {
        $data = $this->validated($request);
        Author::create($data);

        return back()->with('status', 'Author added.');
    }

    public function update(Request $request, Author $author)
    {
        $author->update($this->validated($request, $author));

        return back()->with('status', 'Author updated.');
    }

    public function destroy(Author $author)
    {
        // Articles keep existing but lose their author link (nullOnDelete).
        $author->delete();

        return back()->with('status', 'Author deleted.');
    }

    private function validated(Request $request, ?Author $author = null): array
    {
        $v = $request->validate([
            'name' => ['required', 'string', 'max:255', Rule::unique('authors', 'name')->ignore($author)],
            'role' => ['nullable', 'string', 'max:100'],
            'bio' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:4096'],
        ]);

        $data = [
            'name' => $v['name'],
            'slug' => Str::slug($v['name']),
            'role' => $v['role'] ?? null,
            'bio' => $v['bio'] ?? null,
        ];

        if ($request->hasFile('photo')) {
            $photo = $request->file('photo');
            $data['photo'] = $photo->storeAs('authors', $photo->getClientOriginalName(), 'public');
        }

        return $data;
    }
}
