@if ($listing->hasEntries())
    <div class="overflow-hidden rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <div class="overflow-x-auto">
            <table class="min-w-full divide-y divide-gray-950/5 dark:divide-white/10">
                <thead class="bg-gray-50/80 dark:bg-white/5">
                    <tr>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Name</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Size</th>
                        <th class="px-6 py-4 text-left text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Last Modified</th>
                        <th class="px-6 py-4 text-right text-xs font-semibold uppercase tracking-wide text-gray-500 dark:text-gray-400">Actions</th>
                    </tr>
                </thead>

                <tbody class="divide-y divide-gray-950/5 dark:divide-white/10">
                    @foreach ($listing->entries as $entry)
                        <tr wire:key="browser-table-{{ $entry->path !== '' ? md5($entry->path) : 'root' }}">
                            <td class="px-6 py-4 align-top">
                                <div class="flex items-start gap-3">
                                    <x-filament::icon
                                        :icon="$entry->isDirectory ? 'heroicon-o-folder' : 'heroicon-o-document'"
                                        class="{{ $entry->isDirectory ? 'text-primary-600 dark:text-primary-400' : 'text-gray-500 dark:text-gray-400' }} mt-0.5 h-5 w-5"
                                    />

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
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ $entry->size !== null ? \Illuminate\Support\Number::fileSize($entry->size) : '—' }}
                            </td>

                            <td class="px-6 py-4 text-sm text-gray-600 dark:text-gray-300">
                                {{ $entry->lastModified?->setTimezone(config('app.timezone'))->format('Y-m-d H:i:s') ?? '—' }}
                            </td>

                            <td class="px-6 py-4 text-right">
                                @include('filament-s3-browser::filament-s3-browser.components.entry-actions', ['entry' => $entry])
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    </div>
@else
    <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
        <h3 class="text-base font-semibold text-gray-950 dark:text-white">No items found</h3>
        <p class="mt-2 text-sm text-gray-500 dark:text-gray-400">
            This folder does not contain any visible files or directories for the current search and sort settings.
        </p>
    </div>
@endif
