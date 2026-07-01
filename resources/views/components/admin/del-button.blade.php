@props(['action'])
<form method="POST" action="{{ $action }}" onsubmit="return confirm('Remove this item?')" class="shrink-0">
    @csrf @method('DELETE')
    <button type="submit" class="rounded-lg p-2 text-gray-400 hover:bg-red-50 hover:text-red-600" title="Remove">
        <svg class="h-4 w-4" fill="none" stroke="currentColor" stroke-width="1.7" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M4 7h16M9 7V5a1 1 0 0 1 1-1h4a1 1 0 0 1 1 1v2m1 0v12a1 1 0 0 1-1 1H8a1 1 0 0 1-1-1V7"/></svg>
    </button>
</form>
