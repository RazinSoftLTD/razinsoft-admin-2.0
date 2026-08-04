<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MediaLibrary;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * One place to see every file the panel has stored.
 *
 * The files live in a dozen modules, each of which only shows its own, so "where did that PDF go"
 * had no answer short of an SSH session. This lists all of them by module and type.
 */
class GalleryController extends Controller
{
    public function index(Request $request)
    {
        $canPrivate = $request->user()->allows('gallery', 'private');

        $files = MediaLibrary::all();
        // The private disk holds invoice PDFs and product source files; it is a separate permission
        // and it is filtered before anything else, so no count or filter can hint at what is there.
        if (! $canPrivate) {
            $files = $files->where('disk', 'public');
        }

        // Counts come from the unfiltered set, so the sidebar totals do not move as you filter.
        $categories = $files
            ->groupBy(fn ($f) => MediaLibrary::categoryKey($f['disk'], $f['folder']))
            ->map(fn ($g, $k) => [
                'key' => $k,
                'label' => MediaLibrary::categoryLabel(Str::before($k, ':'), Str::after($k, ':')),
                'count' => $g->count(),
                'size' => $g->sum('size'),
            ])
            ->sortByDesc('count')
            ->values();

        $kinds = collect(MediaLibrary::kindLabels())
            ->map(fn ($label, $key) => [
                'key' => $key,
                'label' => $label,
                'count' => $files->where('kind', $key)->count(),
            ])
            ->values();

        $filtered = $files;
        if ($cat = $request->query('category')) {
            $filtered = $filtered->filter(fn ($f) => MediaLibrary::categoryKey($f['disk'], $f['folder']) === $cat);
        }
        if ($kind = $request->query('kind')) {
            $filtered = $filtered->where('kind', $kind);
        }
        if ($search = trim((string) $request->query('search'))) {
            $filtered = $filtered->filter(fn ($f) => Str::contains(Str::lower($f['path']), Str::lower($search)));
        }

        $filtered = match ($request->query('sort')) {
            'oldest' => $filtered->sortBy('modified'),
            'largest' => $filtered->sortByDesc('size'),
            'name' => $filtered->sortBy('name'),
            default => $filtered->sortByDesc('modified'),
        };

        $filtered = $filtered->values();
        $perPage = 48;
        $page = max(1, (int) $request->query('page', 1));

        $paginator = new LengthAwarePaginator(
            $filtered->forPage($page, $perPage),
            $filtered->count(),
            $perPage,
            $page,
            ['path' => $request->url(), 'query' => $request->query()],
        );

        return view('admin.gallery.index', [
            'files' => $paginator,
            'categories' => $categories,
            'kinds' => $kinds,
            'totalCount' => $files->count(),
            'totalSize' => $files->sum('size'),
            'shownSize' => $filtered->sum('size'),
        ]);
    }

    /**
     * Serve one file.
     *
     * Public files already have a URL, so this exists for the private disk — and it checks the
     * path against the index rather than trusting the query string, which is what stops
     * "../../.env" from being a download link.
     */
    public function file(Request $request)
    {
        $disk = (string) $request->query('disk');
        $path = (string) $request->query('path');

        if ($disk === 'local' && ! $request->user()->allows('gallery', 'private')) {
            abort(403);
        }

        $known = MediaLibrary::all()
            ->first(fn ($f) => $f['disk'] === $disk && $f['path'] === $path);

        abort_unless($known, 404);

        return Storage::disk($disk)->response(
            $path,
            $known['name'],
            ['Content-Disposition' => $request->boolean('download') ? 'attachment' : 'inline'],
        );
    }

    /** Rescan. The index is cached, so a file added a minute ago needs this to show up. */
    public function refresh()
    {
        MediaLibrary::forget();
        MediaLibrary::all();

        return back()->with('status', 'Gallery rescanned.');
    }
}
