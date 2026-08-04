<?php

namespace App\Support;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Every file the panel has stored, in one list.
 *
 * Read from the disk rather than from the tables that reference the files. Thirty-three tables
 * carry a path column, so an index built from them would be thirty-three code paths to keep in
 * step — and it would still miss anything left behind when its row was deleted, which is exactly
 * the sort of thing you open a gallery to find. The top-level folder each module writes into is
 * already the category, so scanning gives that for free.
 */
class MediaLibrary
{
    private const CACHE_KEY = 'gallery.index';

    private const CACHE_TTL = 600;

    /** Folder => what to call it. Anything not listed is shown title-cased. */
    private const CATEGORY_LABELS = [
        'products' => 'Products',
        'articles' => 'Blog & Articles',
        'authors' => 'Authors',
        'avatars' => 'Avatars',
        'chat' => 'Team Chat',
        'deals' => 'Deals',
        'invoices' => 'Invoices',
        'projects' => 'Projects',
        'promotions' => 'Promotions',
        // Two folders hold employee files — 'staff' from the older uploader, 'employees' from the
        // profile one. Distinct labels, or the filter shows "Employees" twice and each chip only
        // finds half of them.
        'staff' => 'Employees',
        'employees' => 'Employee Documents',
        'tickets' => 'Tickets',
        'whatsapp' => 'WhatsApp Media',
        'sources' => 'Product Source Files',
        'licenses' => 'Licenses',
        'finance' => 'Finance',
        'clients' => 'Clients',
    ];

    /**
     * Extension => kind.
     *
     * Some of these are not extensions at all. WhatsApp media is saved using the tail of the MIME
     * type, so the disk holds files ending in "document", "sheet", "x-zip-compressed" and
     * "package-archive". Sorting by a guessed extension alone would file those under "other" and
     * bury a few hundred real documents, so the odd ones are mapped explicitly.
     */
    private const KINDS = [
        'image' => ['jpg', 'jpeg', 'png', 'webp', 'gif', 'svg', 'bmp', 'avif', 'heic'],
        'document' => ['pdf', 'doc', 'docx', 'document', 'xls', 'xlsx', 'sheet', 'ppt', 'pptx',
            'presentation', 'txt', 'plain', 'csv', 'rtf', 'odt'],
        'media' => ['mp4', 'mov', 'quicktime', 'webm', 'avi', 'mkv', 'mp3', 'ogg', 'oga', 'wav', 'm4a', 'aac', 'opus'],
        'archive' => ['zip', 'x-zip-compressed', 'package-archive', 'rar', '7z', 'tar', 'gz'],
    ];

    public static function kindLabels(): array
    {
        return [
            'image' => 'Images',
            'document' => 'PDF & documents',
            'media' => 'Video & audio',
            'archive' => 'Archives',
            'other' => 'Other',
        ];
    }

    /**
     * The whole index, cached — a scan of two thousand files is not worth repeating per keystroke.
     *
     * Plain arrays go into the cache, not the Collection: the store is the database, so the value
     * is serialised, and handing it an object means the read depends on that class unserialising
     * cleanly in whatever process picks it up. It did not, and the page died on a cache hit rather
     * than on the write, which is the sort of failure that only shows up once the cache is warm.
     */
    public static function all(): Collection
    {
        return collect(Cache::remember(self::CACHE_KEY, self::CACHE_TTL, fn () => self::scan()->all()));
    }

    public static function forget(): void
    {
        Cache::forget(self::CACHE_KEY);
    }

    /** @return Collection<int, array> */
    private static function scan(): Collection
    {
        $out = collect();

        foreach (['public', 'local'] as $disk) {
            $fs = Storage::disk($disk);

            foreach ($fs->allFiles() as $path) {
                // Nothing useful and often invisible in a listing — skip rather than show a row
                // the viewer cannot act on.
                if (Str::startsWith(basename($path), '.')) {
                    continue;
                }

                $folder = Str::before($path, '/');
                $ext = Str::lower(pathinfo($path, PATHINFO_EXTENSION));

                $out->push([
                    'disk' => $disk,
                    'path' => $path,
                    'name' => basename($path),
                    'folder' => $folder === $path ? '_root' : $folder,
                    'ext' => $ext,
                    'kind' => self::kind($ext),
                    'size' => (int) $fs->size($path),
                    'modified' => (int) $fs->lastModified($path),
                    // Private files never get a URL: the whole point of that disk is that they are
                    // not reachable without going through a permission check.
                    'url' => $disk === 'public' ? $fs->url($path) : null,
                ]);
            }
        }

        return $out->sortByDesc('modified')->values();
    }

    private static function kind(string $ext): string
    {
        foreach (self::KINDS as $kind => $exts) {
            if (in_array($ext, $exts, true)) {
                return $kind;
            }
        }

        return 'other';
    }

    /** Category key => label, for the filter. Private folders are prefixed so they cannot collide. */
    public static function categoryLabel(string $disk, string $folder): string
    {
        $label = self::CATEGORY_LABELS[$folder] ?? Str::headline($folder);

        return $disk === 'public' ? $label : $label.' (private)';
    }

    public static function categoryKey(string $disk, string $folder): string
    {
        return $disk.':'.$folder;
    }

    /** Human file size. */
    public static function size(int $bytes): string
    {
        if ($bytes < 1024) {
            return $bytes.' B';
        }
        $units = ['KB', 'MB', 'GB'];
        $i = -1;
        do {
            $bytes /= 1024;
            $i++;
        } while ($bytes >= 1024 && $i < 2);

        return round($bytes, $bytes >= 10 ? 0 : 1).' '.$units[$i];
    }
}
