@if ($listing->hasEntries())
    <div class="grid gap-4 sm:grid-cols-2 2xl:grid-cols-3">
        @foreach ($listing->entries as $entry)
            <div
                wire:key="browser-grid-{{ $entry->path !== '' ? md5($entry->path) : 'root' }}"
                class="rounded-2xl bg-white p-5 shadow-sm ring-1 ring-gray-950/5 transition hover:shadow-md dark:bg-gray-900 dark:ring-white/10"
            >
                <div class="flex items-start justify-between gap-3">
                    <div class="flex min-w-0 items-start gap-3">
                        <div class="rounded-xl {{ $entry->isDirectory ? 'bg-primary-50 text-primary-700 dark:bg-primary-500/10 dark:text-primary-300' : 'bg-gray-100 text-gray-700 dark:bg-white/5 dark:text-gray-300' }} p-3">
                            <x-filament::icon
                                :icon="$entry->isDirectory ? 'heroicon-o-folder' : 'heroicon-o-document'"
                                class="h-5 w-5"
                            />
                        </div>

                        <div class="min-w-0">
                            @if ($entry->isDirectory)
                                <button
                                    type="button"
                                    x-on:click="$wire.openDirectory(@js($entry->path))"
                                    class="truncate text-left text-sm font-semibold text-primary-700 transition hover:text-primary-600 dark:text-primary-300 dark:hover:text-primary-200"
                                >
                                    {{ $entry->name }}
                                </button>
                            @else
                                <button
                                    type="button"
                                    x-on:click="$wire.showMetadata(@js($entry->path))"
                                    class="truncate text-left text-sm font-semibold text-gray-950 transition hover:text-primary-600 dark:text-white dark:hover:text-primary-300"
                                >
                                    {{ $entry->name }}
                                </button>
                            @endif

                            <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">
                                {{ $entry->isDirectory ? 'Directory' : ($entry->mimeType ?? 'File') }}
                            </p>
                        </div>
                    </div>

                    @include('filament-s3-browser::filament-s3-browser.components.entry-actions', ['entry' => $entry])
                </div>

                <div class="mt-5 grid gap-3 rounded-xl bg-gray-50/80 p-4 text-sm dark:bg-white/5">
                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">Size</span>
                        <span class="font-medium text-gray-950 dark:text-white">
                            {{ $entry->size !== null ? \Illuminate\Support\Number::fileSize($entry->size) : '—' }}
                        </span>
                    </div>

                    <div class="flex items-center justify-between gap-3">
                        <span class="text-gray-500 dark:text-gray-400">Last Modified</span>
                        <span class="font-medium text-gray-950 dark:text-white">
                            {{ $entry->lastModified?->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}
                        </span>
                    </div>
                </div>
            </div>
        @endforeach
    </div>
@else
    <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">No items found</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            This folder does not contain any visible files or directories for the current search and sort settings.
        </p>
    </div>
@endif
