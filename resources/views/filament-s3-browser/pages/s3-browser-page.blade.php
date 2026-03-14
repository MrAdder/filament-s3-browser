<x-filament-panels::page>
    @php($listing = $this->getListing())

    <div
        x-data
        x-on:filament-s3-browser-copy.window="
            if ($event.detail.content) {
                navigator.clipboard.writeText($event.detail.content)
            }
        "
        class="space-y-6"
    >
        @if ($this->hasAvailableDisks())
            <div class="grid gap-6 xl:grid-cols-[minmax(0,2fr)_minmax(320px,1fr)]">
                <div class="space-y-6">
                    <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                        <div class="grid gap-4 p-6 lg:grid-cols-[180px_minmax(0,1fr)]">
                            <label class="block">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">Disk</span>

                                <select
                                    wire:model.live="disk"
                                    class="mt-2 block w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 transition focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/15 dark:focus:bg-white/10"
                                >
                                    @foreach ($this->availableDisks() as $disk => $label)
                                        <option value="{{ $disk }}">{{ $label }}</option>
                                    @endforeach
                                </select>
                            </label>

                            <div class="space-y-2">
                                <div class="flex flex-wrap items-center gap-2">
                                    <span class="text-sm font-medium text-gray-950 dark:text-white">Breadcrumbs</span>

                                    @if ($listing->rootPrefix !== '')
                                        <span class="rounded-full bg-primary-50 px-3 py-1 text-xs font-medium text-primary-700 dark:bg-primary-500/10 dark:text-primary-300">
                                            Scoped root: {{ $listing->rootPrefix }}
                                        </span>
                                    @endif
                                </div>

                                <nav class="flex flex-wrap items-center gap-2 text-sm text-gray-500 dark:text-gray-400">
                                    @foreach ($listing->breadcrumbs as $breadcrumb)
                                        <button
                                            type="button"
                                            x-on:click="$wire.navigateTo(@js($breadcrumb->path))"
                                            class="{{ $loop->last ? 'font-semibold text-gray-950 dark:text-white' : 'transition hover:text-primary-600 dark:hover:text-primary-400' }}"
                                        >
                                            {{ $breadcrumb->label }}
                                        </button>

                                        @unless ($loop->last)
                                            <span>/</span>
                                        @endunless
                                    @endforeach
                                </nav>

                                <p class="text-xs text-gray-500 dark:text-gray-400">
                                    {{ count($listing->entries) }} visible item{{ count($listing->entries) === 1 ? '' : 's' }} on this level.
                                </p>
                            </div>
                        </div>

                        <div class="grid gap-4 border-t border-gray-950/5 p-6 dark:border-white/10 lg:grid-cols-[minmax(0,1fr)_180px_160px_auto]">
                            <label class="block">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">Search</span>

                                <input
                                    type="search"
                                    wire:model.live.debounce.300ms="search"
                                    placeholder="Search files and folders..."
                                    class="mt-2 block w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 transition focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/15 dark:focus:bg-white/10"
                                />
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">Sort by</span>

                                <select
                                    wire:model.live="sortBy"
                                    class="mt-2 block w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 transition focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/15 dark:focus:bg-white/10"
                                >
                                    <option value="name">Name</option>
                                    <option value="size">Size</option>
                                    <option value="last_modified">Last Modified</option>
                                </select>
                            </label>

                            <label class="block">
                                <span class="text-sm font-medium text-gray-950 dark:text-white">Direction</span>

                                <select
                                    wire:model.live="sortDirection"
                                    class="mt-2 block w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 transition focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/15 dark:focus:bg-white/10"
                                >
                                    <option value="asc">Ascending</option>
                                    <option value="desc">Descending</option>
                                </select>
                            </label>

                            <div class="flex items-end justify-end gap-2">
                                <button
                                    type="button"
                                    wire:click="$set('viewMode', 'table')"
                                    class="{{ $viewMode === 'table' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-50 text-gray-600 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15' }} inline-flex items-center rounded-xl px-4 py-3 text-sm font-medium transition hover:opacity-90"
                                >
                                    Table
                                </button>

                                <button
                                    type="button"
                                    wire:click="$set('viewMode', 'grid')"
                                    class="{{ $viewMode === 'grid' ? 'bg-primary-600 text-white shadow-sm' : 'bg-gray-50 text-gray-600 ring-1 ring-gray-950/10 dark:bg-white/5 dark:text-gray-300 dark:ring-white/15' }} inline-flex items-center rounded-xl px-4 py-3 text-sm font-medium transition hover:opacity-90"
                                >
                                    Grid
                                </button>
                            </div>
                        </div>
                    </div>

                    @if ($showUploadPanel)
                        <div class="rounded-2xl bg-white shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                            <div class="flex flex-wrap items-start justify-between gap-3 border-b border-gray-950/5 p-6 dark:border-white/10">
                                <div>
                                    <h3 class="text-base font-semibold text-gray-950 dark:text-white">Upload Files</h3>
                                    <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                                        Files upload into the current folder by default. Use the optional subdirectory field to place them deeper inside the current path.
                                    </p>
                                </div>

                                <button
                                    type="button"
                                    wire:click="toggleUploadPanel"
                                    class="inline-flex items-center rounded-xl bg-gray-50 px-4 py-2 text-sm font-medium text-gray-700 ring-1 ring-inset ring-gray-950/10 transition hover:bg-gray-100 dark:bg-white/5 dark:text-gray-200 dark:ring-white/10 dark:hover:bg-white/10"
                                >
                                    Close
                                </button>
                            </div>

                            <div class="grid gap-4 p-6 lg:grid-cols-[minmax(0,1fr)_260px_auto]">
                                <label class="block">
                                    <span class="text-sm font-medium text-gray-950 dark:text-white">Files</span>

                                    <input
                                        type="file"
                                        wire:model="uploadFiles"
                                        multiple
                                        class="mt-2 block w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 transition file:mr-4 file:rounded-lg file:border-0 file:bg-primary-600 file:px-3 file:py-2 file:text-sm file:font-medium file:text-white hover:file:bg-primary-500 focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/15 dark:file:bg-primary-500 dark:focus:bg-white/10"
                                    />

                                    @error('uploadFiles')
                                        <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                    @enderror

                                    @error('uploadFiles.*')
                                        <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                    @enderror
                                </label>

                                <label class="block">
                                    <span class="text-sm font-medium text-gray-950 dark:text-white">Optional subdirectory</span>

                                    <input
                                        type="text"
                                        wire:model.live="uploadTargetDirectory"
                                        placeholder="archives/2026"
                                        class="mt-2 block w-full rounded-xl border-0 bg-gray-50 px-4 py-3 text-sm text-gray-950 ring-1 ring-inset ring-gray-950/10 transition focus:bg-white focus:outline-none focus:ring-2 focus:ring-primary-500 dark:bg-white/5 dark:text-white dark:ring-white/15 dark:focus:bg-white/10"
                                    />

                                    @error('uploadTargetDirectory')
                                        <p class="mt-2 text-sm text-danger-600 dark:text-danger-400">{{ $message }}</p>
                                    @enderror
                                </label>

                                <div class="flex items-end">
                                    <button
                                        type="button"
                                        wire:click="submitUpload"
                                        class="inline-flex w-full items-center justify-center rounded-xl bg-primary-600 px-5 py-3 text-sm font-medium text-white shadow-sm transition hover:bg-primary-500 disabled:cursor-not-allowed disabled:opacity-60"
                                    >
                                        Upload
                                    </button>
                                </div>
                            </div>

                            <div
                                wire:loading
                                wire:target="uploadFiles,submitUpload"
                                class="border-t border-gray-950/5 px-6 py-3 text-sm text-primary-700 dark:border-white/10 dark:text-primary-300"
                            >
                                Uploading...
                            </div>
                        </div>
                    @endif

                    @if ($viewMode === 'grid')
                        @include('filament-s3-browser::filament-s3-browser.components.entries-grid', ['listing' => $listing])
                    @else
                        @include('filament-s3-browser::filament-s3-browser.components.entries-table', ['listing' => $listing])
                    @endif
                </div>

                <div>
                    @include('filament-s3-browser::filament-s3-browser.components.metadata-panel', [
                        'metadata' => $selectedMetadata,
                        'selectedMetadataPath' => $selectedMetadataPath,
                    ])
                </div>
            </div>
        @else
            <div class="rounded-2xl bg-white p-8 shadow-sm ring-1 ring-gray-950/5 dark:bg-gray-900 dark:ring-white/10">
                <h3 class="text-lg font-semibold text-gray-950 dark:text-white">No Filesystem Disks Available</h3>
                <p class="mt-2 max-w-2xl text-sm text-gray-500 dark:text-gray-400">
                    Configure at least one Laravel filesystem disk in <code>config/filesystems.php</code> or add browser-specific disk entries in <code>config/filament-s3-browser.php</code> to start browsing files.
                </p>
            </div>
        @endif
    </div>
</x-filament-panels::page>
