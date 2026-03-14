<div class="space-y-5">
    <div class="rounded-2xl bg-gray-50/80 p-4 text-sm dark:bg-white/5">
        <div class="grid gap-3 sm:grid-cols-2">
            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Path</p>
                <p class="mt-2 break-all font-medium text-gray-950 dark:text-white">{{ $preview->path }}</p>
            </div>

            <div>
                <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">MIME Type</p>
                <p class="mt-2 font-medium text-gray-950 dark:text-white">{{ $preview->mimeType ?? 'Unavailable' }}</p>
            </div>
        </div>
    </div>

    @if ($preview->mode === \MrAdder\FilamentS3Browser\Data\FilePreview::MODE_IMAGE && $preview->url !== null)
        <div class="overflow-hidden rounded-2xl bg-gray-950 p-2">
            <img
                src="{{ $preview->url }}"
                alt="{{ $preview->name }}"
                class="max-h-[70vh] w-full rounded-xl object-contain"
            />
        </div>
    @elseif ($preview->mode === \MrAdder\FilamentS3Browser\Data\FilePreview::MODE_TEXT)
        <div class="overflow-hidden rounded-2xl ring-1 ring-gray-950/10 dark:ring-white/10">
            <pre class="max-h-[70vh] overflow-auto bg-gray-950 p-5 text-xs leading-6 text-gray-100">{{ $preview->textContent }}</pre>
        </div>
    @else
        <div class="rounded-2xl border border-dashed border-gray-950/10 bg-gray-50/80 p-6 text-sm text-gray-600 dark:border-white/10 dark:bg-white/5 dark:text-gray-300">
            {{ $preview->message ?? 'Preview content is not available for this file.' }}
        </div>
    @endif

    <div class="grid gap-4 sm:grid-cols-3">
        <div class="rounded-2xl bg-gray-50/80 p-4 dark:bg-white/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</p>
            <p class="mt-2 font-medium text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::headline($preview->metadata['type'] ?? 'file') }}</p>
        </div>

        <div class="rounded-2xl bg-gray-50/80 p-4 dark:bg-white/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Size</p>
            <p class="mt-2 font-medium text-gray-950 dark:text-white">
                {{ isset($preview->metadata['size']) && $preview->metadata['size'] !== null ? \Illuminate\Support\Number::fileSize((int) $preview->metadata['size']) : '—' }}
            </p>
        </div>

        <div class="rounded-2xl bg-gray-50/80 p-4 dark:bg-white/5">
            <p class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Visibility</p>
            <p class="mt-2 font-medium text-gray-950 dark:text-white">{{ $preview->metadata['visibility'] ?? 'Unavailable' }}</p>
        </div>
    </div>
</div>
