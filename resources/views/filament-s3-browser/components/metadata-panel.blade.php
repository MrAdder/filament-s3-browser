<div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10 xl:sticky xl:top-6">
    <div class="flex items-start justify-between gap-3 border-b border-gray-950/5 p-6 dark:border-white/10">
        <div>
            <h3 class="text-base font-semibold text-gray-950 dark:text-white">Metadata</h3>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Inspect details for the selected file or directory.
            </p>
        </div>

        @if ($selectedMetadataPath !== null)
            <button
                type="button"
                x-on:click="$wire.clearMetadata()"
                class="inline-flex items-center rounded-xl bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-950/10 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
            >
                Clear
            </button>
        @endif
    </div>

    @if ($metadata !== [])
        <dl class="grid gap-4 p-6 text-sm">
            <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Path</dt>
                <dd class="mt-2 break-all font-medium text-gray-950 dark:text-white">{{ $metadata['path'] === '' ? '/' : $metadata['path'] }}</dd>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Type</dt>
                    <dd class="mt-2 font-medium text-gray-950 dark:text-white">{{ \Illuminate\Support\Str::headline($metadata['type']) }}</dd>
                </div>

                <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Visibility</dt>
                    <dd class="mt-2 font-medium text-gray-950 dark:text-white">{{ $metadata['visibility'] ?? 'Unavailable' }}</dd>
                </div>
            </div>

            <div class="grid gap-4 sm:grid-cols-2">
                <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Size</dt>
                    <dd class="mt-2 font-medium text-gray-950 dark:text-white">
                        {{ $metadata['size'] !== null ? \Illuminate\Support\Number::fileSize($metadata['size']) : '—' }}
                    </dd>
                </div>

                <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Modified</dt>
                    <dd class="mt-2 font-medium text-gray-950 dark:text-white">
                        {{ $metadata['last_modified']?->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}
                    </dd>
                </div>
            </div>

            <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">MIME Type</dt>
                <dd class="mt-2 font-medium text-gray-950 dark:text-white">{{ $metadata['mime_type'] ?? 'Unavailable' }}</dd>
            </div>

            @if (filled($metadata['public_url'] ?? null))
                <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Public URL</dt>
                    <dd class="mt-2 break-all">
                        <a
                            href="{{ $metadata['public_url'] }}"
                            target="_blank"
                            rel="noreferrer noopener"
                            class="font-medium text-primary-700 transition hover:text-primary-600 dark:text-primary-300 dark:hover:text-primary-200"
                        >
                            {{ $metadata['public_url'] }}
                        </a>
                    </dd>
                </div>
            @endif

            @if (filled($metadata['temporary_url'] ?? null))
                <div class="rounded-xl bg-gray-50/80 p-4 dark:bg-white/5">
                    <dt class="text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Temporary URL</dt>
                    <dd class="mt-2 break-all">
                        <a
                            href="{{ $metadata['temporary_url'] }}"
                            target="_blank"
                            rel="noreferrer noopener"
                            class="font-medium text-primary-700 transition hover:text-primary-600 dark:text-primary-300 dark:hover:text-primary-200"
                        >
                            {{ $metadata['temporary_url'] }}
                        </a>
                    </dd>
                </div>
            @endif
        </dl>
    @else
        <div class="p-6">
            <div class="rounded-2xl border border-dashed border-gray-950/10 bg-gray-50/80 p-6 text-sm text-gray-500 dark:border-white/10 dark:bg-white/5 dark:text-gray-400">
                Select a file or directory from the browser to inspect its metadata and generated URLs here.
            </div>
        </div>
    @endif
</div>
