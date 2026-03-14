<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Filament\Pages;

use BackedEnum;
use Filament\Actions\Action;
use Filament\Forms\Components\TextInput;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Filament\Support\Enums\Width;
use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Contracts\Support\Htmlable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Livewire\Attributes\Url;
use Livewire\WithFileUploads;
use MrAdder\FilamentS3Browser\Data\BrowserBreadcrumb;
use MrAdder\FilamentS3Browser\Data\BrowserEntry;
use MrAdder\FilamentS3Browser\Data\BrowserListing;
use MrAdder\FilamentS3Browser\Data\FilePreview;
use MrAdder\FilamentS3Browser\Exceptions\InvalidPathException;
use MrAdder\FilamentS3Browser\Services\BrowserAuthorizationService;
use MrAdder\FilamentS3Browser\Services\FilePreviewService;
use MrAdder\FilamentS3Browser\Services\FilesystemBrowserService;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;
use UnitEnum;

final class S3BrowserPage extends Page
{
    use WithFileUploads;

    protected static ?string $slug = 's3-browser';

    protected static ?string $title = 'S3 Browser';

    protected string $view = 'filament-s3-browser::filament-s3-browser.pages.s3-browser-page';

    protected string|Width|null $maxContentWidth = 'full';

    #[Url]
    public string $disk = '';

    #[Url]
    public string $path = '';

    #[Url]
    public string $search = '';

    #[Url]
    public string $sortBy = 'name';

    #[Url]
    public string $sortDirection = 'asc';

    #[Url]
    public string $viewMode = 'table';

    public bool $showUploadPanel = false;

    /**
     * @var array<int, UploadedFile>
     */
    public array $uploadFiles = [];

    public string $uploadTargetDirectory = '';

    /**
     * @var array<string, mixed>
     */
    public array $selectedMetadata = [];

    public ?string $selectedMetadataPath = null;

    public function mount(): void
    {
        $availableDisks = $this->availableDisks();

        if ($availableDisks === []) {
            $this->disk = '';
            $this->path = '';

            return;
        }

        if (! array_key_exists($this->disk, $availableDisks)) {
            $this->disk = $this->resolveInitialDisk($availableDisks);
        }

        $this->path = $this->normalizePathForState($this->path);
        $this->sortBy = in_array($this->sortBy, ['name', 'size', 'last_modified'], true) ? $this->sortBy : 'name';
        $this->sortDirection = in_array($this->sortDirection, ['asc', 'desc'], true) ? $this->sortDirection : 'asc';
        $this->viewMode = in_array($this->viewMode, ['table', 'grid'], true) ? $this->viewMode : 'table';
    }

    public static function canAccess(): bool
    {
        return app(BrowserAuthorizationService::class)->canAccessAnyDisk(
            auth()->user(),
            self::configuredDisks(),
        );
    }

    public static function shouldRegisterNavigation(): bool
    {
        return (bool) config('filament-s3-browser.navigation.enabled', true);
    }

    public static function getNavigationLabel(): string
    {
        return (string) config('filament-s3-browser.navigation.label', 'S3 Browser');
    }

    public static function getNavigationIcon(): string|BackedEnum|Htmlable|null
    {
        return config('filament-s3-browser.navigation.icon', 'heroicon-o-cloud');
    }

    public static function getNavigationGroup(): string|UnitEnum|null
    {
        return config('filament-s3-browser.navigation.group');
    }

    public static function getNavigationSort(): ?int
    {
        $sort = config('filament-s3-browser.navigation.sort');

        return is_int($sort) ? $sort : null;
    }

    public function getSubheading(): string
    {
        if ($this->disk === '') {
            return 'No accessible disks are currently available to browse.';
        }

        return sprintf('Browsing the [%s] disk', $this->disk);
    }

    /**
     * @return array<string, string>
     */
    public function availableDisks(): array
    {
        $labels = [];

        foreach (self::configuredDisks() as $disk => $configuration) {
            if (! $this->authz()->canView($this->currentUser(), (string) $disk, '', true)) {
                continue;
            }

            $labels[(string) $disk] = filled($configuration['label'] ?? null)
                ? (string) $configuration['label']
                : Str::of((string) $disk)->replace(['-', '_'], ' ')->headline()->toString();
        }

        return $labels;
    }

    public function hasAvailableDisks(): bool
    {
        return $this->availableDisks() !== [];
    }

    public function getListing(): BrowserListing
    {
        if ($this->disk === '') {
            return new BrowserListing(
                disk: '',
                path: '',
                rootPrefix: '',
                entries: [],
                breadcrumbs: [
                    new BrowserBreadcrumb(label: 'Root', path: ''),
                ],
            );
        }

        return $this->browser()->list(
            disk: $this->disk,
            path: $this->path,
            search: $this->search,
            sortBy: $this->sortBy,
            sortDirection: $this->sortDirection,
        );
    }

    public function updatedDisk(string $disk): void
    {
        $availableDisks = $this->availableDisks();

        if (! array_key_exists($disk, $availableDisks)) {
            $this->disk = $this->resolveInitialDisk($availableDisks);
        }

        $this->path = '';
        $this->clearMetadata();
    }

    public function updatedPath(string $path): void
    {
        $this->path = $this->normalizePathForState($path);
    }

    public function refreshBrowser(): void
    {
        $this->reloadMetadataIfPossible();

        Notification::make()
            ->success()
            ->title('Browser refreshed')
            ->send();
    }

    public function toggleUploadPanel(): void
    {
        $this->showUploadPanel = ! $this->showUploadPanel;
    }

    public function navigateTo(string $path): void
    {
        $this->path = $this->normalizePathForState($path);
        $this->clearMetadata();
    }

    public function openDirectory(string $path): void
    {
        $this->navigateTo($path);
    }

    public function showMetadata(string $path): void
    {
        if (! $this->authz()->canView($this->currentUser(), $this->disk, $path, false)) {
            $this->notifyWarning('You are not allowed to inspect this item.');

            return;
        }

        try {
            $normalizedPath = $this->browser()->normalizePath($path);

            $this->selectedMetadataPath = $normalizedPath;
            $this->selectedMetadata = $this->browser()->metadata($this->disk, $normalizedPath);
        } catch (Throwable $exception) {
            $this->selectedMetadataPath = null;
            $this->selectedMetadata = [];

            $this->notifyException($exception, 'Unable to load metadata');
        }
    }

    public function clearMetadata(): void
    {
        $this->selectedMetadataPath = null;
        $this->selectedMetadata = [];
    }

    public function submitUpload(): void
    {
        if (! $this->canUpload()) {
            $this->notifyWarning('Uploading is disabled');

            return;
        }

        $this->validate([
            'uploadFiles' => ['required', 'array', 'min:1'],
            'uploadFiles.*' => ['file', 'max:'.(int) config('filament-s3-browser.upload.max_size_kb', 51200)],
            'uploadTargetDirectory' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            $targetDirectory = $this->browser()->joinPaths(
                $this->path,
                $this->browser()->normalizePath($this->uploadTargetDirectory),
            );

            foreach ($this->uploadFiles as $file) {
                $this->browser()->upload(
                    disk: $this->disk,
                    directory: $targetDirectory,
                    file: $file,
                );
            }

            $uploadedCount = count($this->uploadFiles);

            $this->reset('uploadFiles', 'uploadTargetDirectory');
            $this->showUploadPanel = false;
            $this->reloadMetadataIfPossible();

            Notification::make()
                ->success()
                ->title($uploadedCount === 1 ? 'File uploaded' : 'Files uploaded')
                ->body(sprintf('%d item%s uploaded successfully.', $uploadedCount, $uploadedCount === 1 ? ' was' : 's were'))
                ->send();
        } catch (Throwable $exception) {
            $this->notifyException($exception, 'Upload failed');
        }
    }

    public function downloadFile(string $path): ?StreamedResponse
    {
        if (! $this->canDownload($path)) {
            $this->notifyWarning('Downloading is disabled');

            return null;
        }

        return $this->browser()->downloadResponse($this->disk, $path);
    }

    public function copyPath(string $path): void
    {
        if (! $this->authz()->canView($this->currentUser(), $this->disk, $path, false)) {
            $this->notifyWarning('You are not allowed to copy this path.');

            return;
        }

        try {
            $normalizedPath = $this->browser()->normalizePath($path);

            $this->dispatch('filament-s3-browser-copy', content: $normalizedPath);

            Notification::make()
                ->success()
                ->title('Path copied')
                ->body('The browser path has been copied to your clipboard.')
                ->send();
        } catch (Throwable $exception) {
            $this->notifyException($exception, 'Unable to copy the path');
        }
    }

    public function copyPublicUrl(string $path): void
    {
        if (! $this->authz()->canView($this->currentUser(), $this->disk, $path, false)) {
            $this->notifyWarning('You are not allowed to copy a public URL for this file.');

            return;
        }

        try {
            $url = $this->browser()->publicUrl($this->disk, $path);

            if ($url === null) {
                $this->notifyWarning('This disk does not expose a public URL for that file.');

                return;
            }

            $this->dispatch('filament-s3-browser-copy', content: $url);

            Notification::make()
                ->success()
                ->title('Public URL copied')
                ->body('The file URL has been copied to your clipboard.')
                ->send();
        } catch (Throwable $exception) {
            $this->notifyException($exception, 'Unable to copy the public URL');
        }
    }

    public function copySignedUrl(string $path, int $minutes = 5): void
    {
        if (! $this->canDownload($path)) {
            $this->notifyWarning('You are not allowed to create a signed URL for this file.');

            return;
        }

        try {
            $url = $this->browser()->temporaryUrl($this->disk, $path, max($minutes, 1));

            if ($url === null) {
                $this->notifyWarning('Signed URLs are not supported for this disk or file.');

                return;
            }

            $this->dispatch('filament-s3-browser-copy', content: $url);

            Notification::make()
                ->success()
                ->title('Signed URL copied')
                ->body('A temporary signed URL has been copied to your clipboard.')
                ->send();
        } catch (Throwable $exception) {
            $this->notifyException($exception, 'Unable to generate a signed URL');
        }
    }

    /**
     * @return array<int, Action>
     */
    public function actionsForEntry(BrowserEntry $entry): array
    {
        $actions = [];

        if (! $entry->isDirectory && $this->canViewEntry($entry)) {
            $actions[] = $this->cloneAction('preview', $this->entryArguments($entry));
        }

        if (! $entry->isDirectory && $this->canDownload($entry->path)) {
            $actions[] = $this->cloneAction('download', ['path' => $entry->path]);
        }

        if ($this->canRename($entry)) {
            $actions[] = $this->cloneAction('rename', ['path' => $entry->path]);
        }

        if ($this->canMove($entry)) {
            $actions[] = $this->cloneAction('move', ['path' => $entry->path]);
        }

        if ($this->canDelete($entry)) {
            $actions[] = $this->cloneAction('delete', [
                'path' => $entry->path,
                'directory' => $entry->isDirectory,
            ]);
        }

        if ($this->canViewEntry($entry)) {
            $actions[] = $this->cloneAction('copyPath', ['path' => $entry->path]);
            $actions[] = $this->cloneAction('metadata', ['path' => $entry->path]);
        }

        if (! $entry->isDirectory && $this->canViewEntry($entry)) {
            $actions[] = $this->cloneAction('copyPublicUrl', ['path' => $entry->path]);
        }

        if (! $entry->isDirectory && $this->canDownload($entry->path)) {
            $actions[] = $this->cloneAction('generateSignedUrl', ['path' => $entry->path]);
        }

        return array_values(array_filter($actions));
    }

    /**
     * @return array<int, Action>
     */
    protected function getHeaderActions(): array
    {
        if ($this->disk === '') {
            return [];
        }

        $actions = [
            $this->refreshAction(),
        ];

        if ($this->canCreateDirectory()) {
            $actions[] = $this->createDirectoryAction();
        }

        if ($this->canUpload()) {
            $actions[] = $this->toggleUploadPanelAction();
        }

        return $actions;
    }

    protected function refreshAction(): Action
    {
        return Action::make('refresh')
            ->label('Refresh')
            ->icon('heroicon-o-arrow-path')
            ->action(function (): void {
                $this->refreshBrowser();
            });
    }

    protected function toggleUploadPanelAction(): Action
    {
        return Action::make('toggleUploadPanel')
            ->label($this->showUploadPanel ? 'Hide Upload' : 'Upload')
            ->icon('heroicon-o-arrow-up-tray')
            ->color($this->showUploadPanel ? 'gray' : 'primary')
            ->action(function (): void {
                $this->toggleUploadPanel();
            });
    }

    protected function createDirectoryAction(): Action
    {
        return Action::make('createDirectory')
            ->label('New Folder')
            ->icon('heroicon-o-folder-plus')
            ->schema([
                TextInput::make('name')
                    ->label('Folder name')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $data): void {
                try {
                    $targetPath = $this->browser()->joinPaths($this->path, (string) ($data['name'] ?? ''));

                    $this->browser()->makeDirectory($this->disk, $targetPath);

                    Notification::make()
                        ->success()
                        ->title('Folder created')
                        ->send();
                } catch (Throwable $exception) {
                    $this->notifyException($exception, 'Unable to create the folder');
                }
            });
    }

    protected function previewAction(): Action
    {
        return Action::make('preview')
            ->label('Preview')
            ->icon('heroicon-o-eye')
            ->modalSubmitAction(false)
            ->modalCancelActionLabel('Close')
            ->modalHeading(fn (array $arguments): string => $this->previewFromArguments($arguments)->heading())
            ->modalWidth(fn (array $arguments): Width => $this->previewFromArguments($arguments)->modalWidth())
            ->modalHidden(fn (array $arguments): bool => $this->shouldPreviewOpenInNewTab($arguments))
            ->modalContent(function (array $arguments) {
                /** @var view-string $view */
                $view = 'filament-s3-browser::filament-s3-browser.components.preview-modal';

                return view($view, [
                    'preview' => $this->previewFromArguments($arguments),
                ]);
            })
            ->url(
                fn (array $arguments): ?string => $this->shouldPreviewOpenInNewTab($arguments)
                    ? $this->previews()->previewUrl($this->disk, (string) ($arguments['path'] ?? ''))
                    : null,
                shouldOpenInNewTab: true,
            );
    }

    protected function downloadAction(): Action
    {
        return Action::make('download')
            ->label('Download')
            ->icon('heroicon-o-arrow-down-tray')
            ->action(fn (array $arguments) => $this->downloadFile((string) ($arguments['path'] ?? '')));
    }

    protected function renameAction(): Action
    {
        return Action::make('rename')
            ->label('Rename')
            ->icon('heroicon-o-pencil-square')
            ->fillForm(fn (array $arguments): array => [
                'new_name' => $this->browser()->basename((string) ($arguments['path'] ?? '')),
            ])
            ->schema([
                TextInput::make('new_name')
                    ->label('New name')
                    ->required()
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data): void {
                try {
                    $oldPath = (string) ($arguments['path'] ?? '');
                    $newPath = $this->browser()->rename($this->disk, $oldPath, (string) ($data['new_name'] ?? ''));

                    $this->refreshMetadataAfterPathChange($oldPath, $newPath);

                    Notification::make()
                        ->success()
                        ->title('Item renamed')
                        ->send();
                } catch (Throwable $exception) {
                    $this->notifyException($exception, 'Unable to rename the item');
                }
            });
    }

    protected function moveAction(): Action
    {
        return Action::make('move')
            ->label('Move')
            ->icon('heroicon-o-arrows-right-left')
            ->fillForm(fn (array $arguments): array => [
                'destination' => $this->browser()->dirname((string) ($arguments['path'] ?? '')),
            ])
            ->schema([
                TextInput::make('destination')
                    ->label('Destination directory')
                    ->helperText('Use a relative path inside the configured browser root. Leave blank to move to the root.')
                    ->maxLength(255),
            ])
            ->action(function (array $arguments, array $data): void {
                try {
                    $oldPath = (string) ($arguments['path'] ?? '');
                    $newPath = $this->browser()->move($this->disk, $oldPath, (string) ($data['destination'] ?? ''));

                    $this->refreshMetadataAfterPathChange($oldPath, $newPath);

                    Notification::make()
                        ->success()
                        ->title('Item moved')
                        ->send();
                } catch (Throwable $exception) {
                    $this->notifyException($exception, 'Unable to move the item');
                }
            });
    }

    protected function deleteAction(): Action
    {
        return Action::make('delete')
            ->label('Delete')
            ->icon('heroicon-o-trash')
            ->color('danger')
            ->requiresConfirmation()
            ->modalDescription('This action permanently deletes the selected item.')
            ->action(function (array $arguments): void {
                try {
                    $path = (string) ($arguments['path'] ?? '');
                    $isDirectory = (bool) ($arguments['directory'] ?? false);

                    if ($isDirectory) {
                        $this->browser()->deleteDirectory($this->disk, $path);
                    } else {
                        $this->browser()->delete($this->disk, $path);
                    }

                    $this->forgetMetadataIfMatches($path);

                    Notification::make()
                        ->success()
                        ->title('Item deleted')
                        ->send();
                } catch (Throwable $exception) {
                    $this->notifyException($exception, 'Unable to delete the item');
                }
            });
    }

    protected function copyPathAction(): Action
    {
        return Action::make('copyPath')
            ->label('Copy Path')
            ->icon('heroicon-o-clipboard')
            ->action(function (array $arguments): void {
                $this->copyPath((string) ($arguments['path'] ?? ''));
            });
    }

    protected function copyPublicUrlAction(): Action
    {
        return Action::make('copyPublicUrl')
            ->label('Copy Public URL')
            ->icon('heroicon-o-link')
            ->action(function (array $arguments): void {
                $this->copyPublicUrl((string) ($arguments['path'] ?? ''));
            });
    }

    protected function generateSignedUrlAction(): Action
    {
        return Action::make('generateSignedUrl')
            ->label('Generate Signed URL')
            ->icon('heroicon-o-clock')
            ->fillForm(fn (): array => [
                'minutes' => (int) config('filament-s3-browser.temporary_urls.ttl', 5),
            ])
            ->schema([
                TextInput::make('minutes')
                    ->label('Expires in (minutes)')
                    ->numeric()
                    ->minValue(1)
                    ->required(),
            ])
            ->action(function (array $arguments, array $data): void {
                $this->copySignedUrl(
                    (string) ($arguments['path'] ?? ''),
                    (int) ($data['minutes'] ?? 5),
                );
            });
    }

    protected function metadataAction(): Action
    {
        return Action::make('metadata')
            ->label('View Metadata')
            ->icon('heroicon-o-information-circle')
            ->action(function (array $arguments): void {
                $this->showMetadata((string) ($arguments['path'] ?? ''));
            });
    }

    private function authz(): BrowserAuthorizationService
    {
        return app(BrowserAuthorizationService::class);
    }

    private function previews(): FilePreviewService
    {
        return app(FilePreviewService::class);
    }

    private function browser(): FilesystemBrowserService
    {
        return app(FilesystemBrowserService::class);
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    private static function configuredDisks(): array
    {
        $configuredDisks = config('filament-s3-browser.disks', []);

        if (is_array($configuredDisks) && $configuredDisks !== []) {
            return $configuredDisks;
        }

        $filesystemDisks = config('filesystems.disks', []);

        return is_array($filesystemDisks) ? $filesystemDisks : [];
    }

    private function currentUser(): ?Authenticatable
    {
        $user = auth()->user();

        return $user instanceof Authenticatable ? $user : null;
    }

    /**
     * @param  array<string, string>  $availableDisks
     */
    private function resolveInitialDisk(array $availableDisks): string
    {
        $defaultDisk = (string) config('filament-s3-browser.default_disk', 's3');

        if (array_key_exists($defaultDisk, $availableDisks)) {
            return $defaultDisk;
        }

        return (string) array_key_first($availableDisks);
    }

    private function normalizePathForState(string $path): string
    {
        try {
            return $this->browser()->normalizePath($path);
        } catch (InvalidPathException $exception) {
            $this->notifyException($exception, 'The requested path is invalid');

            return '';
        }
    }

    private function canViewEntry(BrowserEntry $entry): bool
    {
        return $this->authz()->canView($this->currentUser(), $this->disk, $entry->path, $entry->isDirectory);
    }

    private function canUpload(): bool
    {
        return $this->authz()->canUpload($this->currentUser(), $this->disk, $this->path);
    }

    private function canRename(BrowserEntry $entry): bool
    {
        return $this->authz()->canRename($this->currentUser(), $this->disk, $entry->path, $entry->isDirectory);
    }

    private function canMove(BrowserEntry $entry): bool
    {
        return $this->authz()->canMove($this->currentUser(), $this->disk, $entry->path, $entry->isDirectory);
    }

    private function canDelete(BrowserEntry $entry): bool
    {
        return $this->authz()->canDelete($this->currentUser(), $this->disk, $entry->path, $entry->isDirectory);
    }

    private function canDownload(string $path): bool
    {
        return $this->authz()->canDownload($this->currentUser(), $this->disk, $path);
    }

    private function canCreateDirectory(): bool
    {
        return $this->authz()->canCreateDirectory($this->currentUser(), $this->disk, $this->path);
    }

    private function refreshMetadataAfterPathChange(string $oldPath, string $newPath): void
    {
        if ($this->selectedMetadataPath !== $this->browser()->normalizePath($oldPath)) {
            return;
        }

        $this->selectedMetadataPath = $this->browser()->normalizePath($newPath);
        $this->selectedMetadata = $this->browser()->metadata($this->disk, $newPath);
    }

    private function reloadMetadataIfPossible(): void
    {
        if ($this->selectedMetadataPath === null) {
            return;
        }

        try {
            $this->selectedMetadata = $this->browser()->metadata($this->disk, $this->selectedMetadataPath);
        } catch (Throwable) {
            $this->clearMetadata();
        }
    }

    private function forgetMetadataIfMatches(string $path): void
    {
        if ($this->selectedMetadataPath === $this->browser()->normalizePath($path)) {
            $this->clearMetadata();
        }
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function previewFromArguments(array $arguments): FilePreview
    {
        return $this->previews()->preview(
            disk: $this->disk,
            path: (string) ($arguments['path'] ?? ''),
            name: isset($arguments['name']) ? (string) $arguments['name'] : null,
            mimeType: isset($arguments['mime_type']) ? (string) $arguments['mime_type'] : null,
            size: isset($arguments['size']) && is_numeric($arguments['size']) ? (int) $arguments['size'] : null,
        );
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function shouldPreviewOpenInNewTab(array $arguments): bool
    {
        $name = isset($arguments['name']) ? (string) $arguments['name'] : '';
        $mimeType = isset($arguments['mime_type']) ? (string) $arguments['mime_type'] : null;

        return $this->previews()->modeFor($name, $mimeType) === FilePreview::MODE_PDF;
    }

    /**
     * @return array{path: string, name: string, mime_type: ?string, size: ?int}
     */
    private function entryArguments(BrowserEntry $entry): array
    {
        return [
            'path' => $entry->path,
            'name' => $entry->name,
            'mime_type' => $entry->mimeType,
            'size' => $entry->size,
        ];
    }

    /**
     * @param  array<string, mixed>  $arguments
     */
    private function cloneAction(string $name, array $arguments): ?Action
    {
        $action = $this->getAction($name, false);

        if (! $action instanceof Action) {
            return null;
        }

        return (clone $action)->arguments($arguments);
    }

    private function notifyException(Throwable $exception, string $title): void
    {
        Notification::make()
            ->danger()
            ->title($title)
            ->body($exception->getMessage())
            ->send();
    }

    private function notifyWarning(string $message): void
    {
        Notification::make()
            ->warning()
            ->title($message)
            ->send();
    }
}
