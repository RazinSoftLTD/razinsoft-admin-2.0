@extends('admin.layouts.app')
@section('title', $article->exists ? 'Edit · ' . $article->title : 'New Article')

@php
    $editing = $article->exists;
    $action = $editing ? route('admin.articles.update', $article) : route('admin.articles.store');
    $tagsText = collect($article->tags ?? [])->implode("\n");
    $takeawaysText = collect($article->takeaways ?? [])->implode("\n");
@endphp

@section('content')
    <a href="{{ route('admin.articles.index') }}" class="mb-4 inline-flex items-center gap-2 text-sm font-semibold text-[var(--color-muted)] hover:text-[var(--color-heading)]">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="2" viewBox="0 0 24 24"><path stroke-linecap="round" d="m15 18-6-6 6-6"/></svg> Back to articles
    </a>

    <div class="mb-4 flex items-center justify-between gap-3">
        <h1 class="text-xl font-bold text-[var(--color-heading)]">{{ $editing ? 'Edit article' : 'New article' }}</h1>
        @if ($editing)
            <div class="flex items-center gap-2">
                <x-admin.status :status="$article->status" />
                @if ($article->status === 'published')
                    <a href="{{ rtrim(config('services.frontend_url'), '/') }}/articles/{{ $article->slug }}" target="_blank" class="text-sm font-semibold text-[var(--color-primary)] hover:underline">Preview ↗</a>
                @endif
            </div>
        @endif
    </div>

    <form id="article-form" method="POST" action="{{ $action }}" enctype="multipart/form-data" class="max-w-4xl space-y-6">
        @csrf
        @if ($editing) @method('PUT') @endif

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Basic info</h3>
            <div class="space-y-5">
                <x-admin.field label="Title" name="title" :value="$article->title" required />
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-admin.field label="Slug" name="slug" :value="$article->slug" hint="Auto from title if blank." />
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Category</label>
                        <select name="category_id" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            <option value="">— Select category —</option>
                            @foreach ($categories as $cat)
                                <option value="{{ $cat->id }}" @selected($article->category_id == $cat->id)>{{ $cat->name }}</option>
                            @endforeach
                        </select>
                        @if ($categories->isEmpty())
                            <p class="mt-1 text-xs text-amber-600">No categories yet — <a href="{{ route('admin.article-categories.index') }}" class="underline">create one first</a>.</p>
                        @else
                            <p class="mt-1 text-xs text-gray-400"><a href="{{ route('admin.article-categories.index') }}" class="text-[var(--color-primary)] hover:underline">Manage categories</a></p>
                        @endif
                    </div>
                </div>
                <x-admin.field label="Excerpt" name="excerpt" type="textarea" :rows="2" :value="$article->excerpt" />
                <div class="grid gap-5 sm:grid-cols-3">
                    <div>
                        <label class="mb-1.5 block text-sm font-medium">Author</label>
                        <select name="author_id" class="h-10 w-full rounded-lg border border-gray-200 px-3 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]">
                            <option value="">— Select author —</option>
                            @foreach ($authors as $au)
                                <option value="{{ $au->id }}" @selected($article->author_id == $au->id)>{{ $au->name }}</option>
                            @endforeach
                        </select>
                        <p class="mt-1 text-xs {{ $authors->isEmpty() ? 'text-amber-600' : 'text-gray-400' }}"><a href="{{ route('admin.authors.index') }}" class="{{ $authors->isEmpty() ? 'underline' : 'text-[var(--color-primary)] hover:underline' }}">{{ $authors->isEmpty() ? 'Create an author first' : 'Manage authors' }}</a></p>
                    </div>
                    <x-admin.field label="Published date" name="published_at" type="date" :value="optional($article->published_at)->toDateString()" />
                    <x-admin.field label="Read time" name="read_time" :value="$article->read_time" placeholder="8 min read" />
                </div>
                <div class="grid gap-5 sm:grid-cols-2">
                    <x-admin.field label="Status" name="status" type="select" :value="$article->status" :options="['draft' => 'Draft', 'published' => 'Published']" required />
                    <div class="flex items-end pb-2">
                        <x-admin.field name="is_featured" type="checkbox" label="Featured article (only one)" :value="$article->is_featured" />
                    </div>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Cover image</h3>
            <div class="grid gap-5 sm:grid-cols-2">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Upload image</label>
                    @if ($article->image)<img src="{{ \App\Http\Resources\ProductResource::media($article->image) }}" class="mb-2 h-24 rounded-lg border border-gray-100 object-cover">@endif
                    <input type="file" name="image" accept="image/*" class="block w-full text-sm text-gray-500 file:mr-3 file:rounded-lg file:border-0 file:bg-[var(--color-primary-soft)] file:px-3 file:py-2 file:text-sm file:font-semibold file:text-[var(--color-primary)]">
                </div>
                <x-admin.field label="…or image URL" name="image_url" value="" hint="Paste an external image URL (used if no file uploaded)." />
            </div>
            <div class="mt-5">
                <x-admin.field label="Cover image alt text" name="image_alt" :value="$article->image_alt" hint="Describes the image for SEO & screen readers. Defaults to the title." />
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">Body</h3>
            <div class="space-y-5">
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Content</label>
                    <input type="hidden" name="content" id="content-input" value="{{ old('content', $article->content) }}">
                    <div id="editor" class="rounded-lg border border-gray-200 bg-white"></div>
                    <p class="mt-1 text-xs text-gray-400">Use the toolbar to format text and insert images anywhere between the text.</p>
                </div>
                <x-admin.field label="Pull-quote (optional)" name="quote" type="textarea" :rows="2" :value="$article->quote" hint="Highlighted quote shown below the article body." />
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Key takeaways (optional)</label>
                    <textarea name="takeaways" rows="4" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]" placeholder="One takeaway per line">{{ $takeawaysText }}</textarea>
                </div>
                <div>
                    <label class="mb-1.5 block text-sm font-medium">Tags</label>
                    <textarea name="tags" rows="2" class="w-full rounded-lg border border-gray-200 px-3 py-2.5 text-sm focus:border-[var(--color-primary)] focus:outline-none focus:ring-1 focus:ring-[var(--color-primary)]" placeholder="One per line or comma-separated">{{ $tagsText }}</textarea>
                </div>
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-1 text-sm font-bold uppercase tracking-wide text-gray-400">Attached products</h3>
            <p class="mb-4 text-xs text-gray-400">Attached products show in the article's left sidebar on the website (replacing author/stats/share). Leave empty and the article uses the full width.</p>
            @php $selectedProducts = old('product_ids', $article->exists ? $article->products->pluck('id')->all() : []); @endphp
            <div class="grid max-h-64 gap-2 overflow-auto sm:grid-cols-2">
                @forelse ($allProducts as $p)
                    <label class="flex items-center gap-2 rounded-lg border border-gray-100 px-3 py-2 text-sm hover:bg-gray-50">
                        <input type="checkbox" name="product_ids[]" value="{{ $p->id }}" @checked(in_array($p->id, $selectedProducts)) class="rounded border-gray-300 text-[var(--color-primary)] focus:ring-[var(--color-primary)]">
                        <span class="truncate">{{ $p->name }}</span>
                    </label>
                @empty
                    <p class="text-sm text-gray-400">No products yet.</p>
                @endforelse
            </div>
        </div>

        <div class="rounded-xl border border-gray-100 bg-white p-6 shadow-sm">
            <h3 class="mb-4 text-sm font-bold uppercase tracking-wide text-gray-400">SEO / Meta</h3>
            <div class="space-y-5">
                <x-admin.field label="Meta title" name="meta_title" :value="$article->meta_title" hint="Defaults to the article title if left blank." />
                <x-admin.field label="Meta description" name="meta_description" type="textarea" :rows="2" :value="$article->meta_description" hint="Defaults to the excerpt if left blank." />
                <x-admin.field label="Meta keywords" name="meta_keywords" :value="$article->meta_keywords" hint="Comma-separated keywords." />
            </div>
        </div>

        <div class="flex gap-3">
            <button class="rounded-lg bg-[var(--color-primary)] px-5 py-2.5 text-sm font-semibold text-white hover:bg-[var(--color-primary-hover)]">{{ $editing ? 'Save changes' : 'Create article' }}</button>
            <a href="{{ route('admin.articles.index') }}" class="rounded-lg border border-gray-200 px-5 py-2.5 text-sm font-semibold text-[var(--color-muted)] hover:bg-gray-50">Cancel</a>
        </div>
    </form>

    {{-- Rich text editor (Quill) --}}
    <link href="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.snow.css" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/quill@2.0.3/dist/quill.js"></script>
    <script>
        document.addEventListener('DOMContentLoaded', function () {
            const quill = new Quill('#editor', {
                theme: 'snow',
                placeholder: 'Write the article… use the toolbar to format text and insert images.',
                modules: {
                    toolbar: {
                        container: [
                            [{ header: [2, 3, false] }],
                            ['bold', 'italic', 'underline'],
                            ['blockquote'],
                            [{ list: 'ordered' }, { list: 'bullet' }],
                            ['link', 'image'],
                            ['clean'],
                        ],
                        handlers: {
                            image: function () {
                                const input = document.createElement('input');
                                input.type = 'file';
                                input.accept = 'image/*';
                                input.onchange = async function () {
                                    const file = input.files[0];
                                    if (!file) return;
                                    const fd = new FormData();
                                    fd.append('file', file);
                                    try {
                                        const res = await fetch('{{ route('admin.articles.upload-image') }}', {
                                            method: 'POST',
                                            headers: { 'X-CSRF-TOKEN': '{{ csrf_token() }}', 'Accept': 'application/json' },
                                            body: fd,
                                        });
                                        const data = await res.json();
                                        const range = quill.getSelection(true);
                                        quill.insertEmbed(range.index, 'image', data.url, 'user');
                                        quill.setSelection(range.index + 1);
                                    } catch (e) {
                                        alert('Image upload failed.');
                                    }
                                };
                                input.click();
                            },
                        },
                    },
                },
            });

            const hidden = document.getElementById('content-input');
            if (hidden.value) quill.root.innerHTML = hidden.value;

            document.getElementById('article-form').addEventListener('submit', function () {
                const html = quill.getText().trim().length || quill.root.querySelector('img') ? quill.root.innerHTML : '';
                hidden.value = html;
            });
        });
    </script>
    <style>#editor{min-height:340px}#editor .ql-editor{min-height:340px;font-size:15px}</style>
@endsection
