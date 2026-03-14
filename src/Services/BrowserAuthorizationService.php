<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Services;

use Illuminate\Contracts\Auth\Access\Gate;
use Illuminate\Contracts\Auth\Authenticatable;
use MrAdder\FilamentS3Browser\Data\BrowserTarget;

final class BrowserAuthorizationService
{
    public function __construct(
        private readonly Gate $gate,
        private readonly FilesystemBrowserService $browser,
    ) {}

    public function canView(?Authenticatable $user, string $disk, string $path = '', bool $isDirectory = true): bool
    {
        return $this->allows($user, 'view', $disk, $path, $isDirectory, 'view');
    }

    public function canUpload(?Authenticatable $user, string $disk, string $path = ''): bool
    {
        return $this->allows($user, 'upload', $disk, $path, true, 'upload');
    }

    public function canRename(?Authenticatable $user, string $disk, string $path, bool $isDirectory = false): bool
    {
        return $this->allows($user, 'rename', $disk, $path, $isDirectory, 'rename');
    }

    public function canMove(?Authenticatable $user, string $disk, string $path, bool $isDirectory = false): bool
    {
        return $this->allows($user, 'move', $disk, $path, $isDirectory, 'move');
    }

    public function canDelete(?Authenticatable $user, string $disk, string $path, bool $isDirectory = false): bool
    {
        return $this->allows($user, 'delete', $disk, $path, $isDirectory, 'delete');
    }

    public function canDownload(?Authenticatable $user, string $disk, string $path): bool
    {
        return $this->allows($user, 'download', $disk, $path, false, 'download');
    }

    public function canCreateDirectory(?Authenticatable $user, string $disk, string $path = ''): bool
    {
        return $this->policyExists()
            ? $this->allows($user, 'upload', $disk, $path, true, 'create_directory')
            : (bool) config('filament-s3-browser.permissions.create_directory', true);
    }

    /**
     * @param  array<string, array<string, mixed>>  $disks
     */
    public function canAccessAnyDisk(?Authenticatable $user, array $disks): bool
    {
        foreach (array_keys($disks) as $disk) {
            if ($this->canView($user, (string) $disk, '', true)) {
                return true;
            }
        }

        return false;
    }

    private function allows(
        ?Authenticatable $user,
        string $ability,
        string $disk,
        string $path,
        bool $isDirectory,
        string $fallbackConfigKey,
    ): bool {
        if (! $this->policyExists()) {
            return (bool) config("filament-s3-browser.permissions.{$fallbackConfigKey}", true);
        }

        $target = new BrowserTarget(
            disk: $disk,
            path: $this->browser->normalizePath($path),
            isDirectory: $isDirectory,
        );

        $gate = $user !== null ? $this->gate->forUser($user) : $this->gate;

        return $gate->inspect($ability, $target)->allowed();
    }

    private function policyExists(): bool
    {
        return $this->gate->getPolicyFor(BrowserTarget::class) !== null;
    }
}
