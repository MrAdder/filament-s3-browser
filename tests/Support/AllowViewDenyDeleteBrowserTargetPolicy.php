<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Tests\Support;

use Illuminate\Contracts\Auth\Authenticatable;
use MrAdder\FilamentS3Browser\Data\BrowserTarget;

final class AllowViewDenyDeleteBrowserTargetPolicy
{
    public function view(Authenticatable $user, BrowserTarget $target): bool
    {
        return $target->disk === 's3';
    }

    public function upload(Authenticatable $user, BrowserTarget $target): bool
    {
        return $target->path !== 'locked';
    }

    public function rename(Authenticatable $user, BrowserTarget $target): bool
    {
        return true;
    }

    public function move(Authenticatable $user, BrowserTarget $target): bool
    {
        return true;
    }

    public function delete(Authenticatable $user, BrowserTarget $target): bool
    {
        return false;
    }

    public function download(Authenticatable $user, BrowserTarget $target): bool
    {
        return true;
    }
}
