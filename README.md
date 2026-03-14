# Filament S3 Browser

Browse and manage S3 buckets directly inside your Filament admin panel.

Filament S3 Browser adds a storage browser to your admin interface so you can
view, upload, move, delete, and preview files stored on S3-compatible storage
without leaving Filament.

It supports Amazon S3, MinIO, Cloudflare R2, Wasabi, DigitalOcean Spaces, and
any other Laravel filesystem disk exposed through the `Storage` facade.

---

## Features

- Browse configured Laravel filesystem disks directly inside Filament
- Navigate folders with breadcrumbs
- Upload files and create folders
- Rename, move, delete, and download files
- Preview images, PDFs, and small text files
- View metadata for binary files and unsupported previews
- Generate temporary signed URLs when the disk supports them
- Copy relative paths and public URLs
- Search entries in the current directory
- Switch between table and grid views
- Sort by name, size, or last modified date
- Restrict browsing to safe root prefixes
- Extend authorization with policies or use config fallbacks

---

## Requirements

- PHP 8.2+
- Laravel 11 or 12
- Filament 4+
- Livewire 3

---

## Installation

Install the package via Composer:

```bash
composer require mradder/filament-s3-browser
```

Publish the configuration file:

```bash
php artisan vendor:publish --tag="filament-s3-browser-config"
```

Register the plugin in your Filament panel provider:

```php
<?php

declare(strict_types=1);

namespace App\Providers\Filament;

use Filament\Panel;
use Filament\PanelProvider;
use MrAdder\FilamentS3Browser\FilamentS3BrowserPlugin;

final class AdminPanelProvider extends PanelProvider
{
    public function panel(Panel $panel): Panel
    {
        return $panel
            ->default()
            ->id('admin')
            ->path('admin')
            ->plugins([
                FilamentS3BrowserPlugin::make(),
            ]);
    }
}
```

---

## Configuration

The package is configured through `config/filament-s3-browser.php`.

Example configuration:

```php
<?php

declare(strict_types=1);

return [
    'disks' => [
        's3' => [
            'label' => 'Assets',
            'root' => 'tenants/acme',
            'temporary_urls' => true,
        ],
        'archive' => [
            'label' => 'Archive',
            'root' => 'archive',
            'temporary_urls' => false,
        ],
    ],

    'default_disk' => 's3',

    'permissions' => [
        'view' => true,
        'upload' => true,
        'rename' => true,
        'move' => true,
        'delete' => true,
        'download' => true,
        'create_directory' => true,
        'set_visibility' => true,
    ],

    'temporary_urls' => [
        'enabled' => true,
        'ttl' => 5,
    ],

    'navigation' => [
        'enabled' => true,
        'group' => 'Storage',
        'label' => 'S3 Browser',
        'icon' => 'heroicon-o-cloud',
        'sort' => 50,
    ],

    'preview' => [
        'text_limit_bytes' => 131072,
        'signed_url_ttl' => 5,
    ],

    'upload' => [
        'max_size_kb' => 51200,
    ],
];
```

### Config notes

- `disks` is keyed by the Laravel disk names defined in `config/filesystems.php`
- `root` limits the browser to a safe subdirectory on that disk
- `temporary_urls.ttl` is expressed in minutes
- `preview.text_limit_bytes` controls how much text can be previewed inline
- `upload.max_size_kb` controls the upload limit enforced by the Filament page

### Example filesystem disks

Any Laravel filesystem disk can be browsed. For S3-compatible services that
use Flysystem's S3 driver, your `config/filesystems.php` may look like this:

```php
'disks' => [
    's3' => [
        'driver' => 's3',
        'key' => env('AWS_ACCESS_KEY_ID'),
        'secret' => env('AWS_SECRET_ACCESS_KEY'),
        'region' => env('AWS_DEFAULT_REGION'),
        'bucket' => env('AWS_BUCKET'),
        'url' => env('AWS_URL'),
        'endpoint' => env('AWS_ENDPOINT'),
        'use_path_style_endpoint' => env('AWS_USE_PATH_STYLE_ENDPOINT', false),
    ],
],
```

That same pattern works for MinIO, Cloudflare R2, Wasabi, and other
S3-compatible providers by changing the credentials, bucket, region, and
endpoint values.

### Root restrictions

If you set a disk root such as `'root' => 'tenants/acme'`, the browser will
normalize every path and prevent traversal above that prefix.

### Temporary URLs

When the disk supports Laravel's `temporaryUrl()` method, the browser can
generate short-lived links for downloads and previews. Image and PDF previews
use an internal signed route so private disks can still be previewed safely.

---

## Usage

After installation, a new Filament navigation item appears by default:

**Storage -> S3 Browser**

From the browser page you can:

- switch between configured disks
- navigate folders with breadcrumbs
- search within the current directory
- refresh the current listing
- upload files
- create folders
- preview supported file types
- rename, move, download, and delete files
- copy relative paths, public URLs, and signed URLs
- inspect file metadata

Folders are listed before files, and users can switch between a table view and
grid view.

---

## Permissions

By default, the package uses the fallback flags in
`filament-s3-browser.permissions`.

For example, this disables uploads and deletes globally:

```php
'permissions' => [
    'upload' => false,
    'delete' => false,
],
```

If you need user-aware authorization, register a Laravel policy for
`MrAdder\FilamentS3Browser\Data\BrowserTarget`.

Example policy:

```php
<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\User;
use MrAdder\FilamentS3Browser\Data\BrowserTarget;

final class BrowserTargetPolicy
{
    public function view(User $user, BrowserTarget $target): bool
    {
        return $user->can('media.view');
    }

    public function upload(User $user, BrowserTarget $target): bool
    {
        return $user->can('media.upload');
    }

    public function rename(User $user, BrowserTarget $target): bool
    {
        return $user->can('media.rename');
    }

    public function move(User $user, BrowserTarget $target): bool
    {
        return $user->can('media.move');
    }

    public function delete(User $user, BrowserTarget $target): bool
    {
        return $user->can('media.delete');
    }

    public function download(User $user, BrowserTarget $target): bool
    {
        return $user->can('media.download');
    }
}
```

Then register the policy in your `AppServiceProvider` or `AuthServiceProvider`.

---

## Preview Support

Supported preview types:

| Type | Behavior |
| --- | --- |
| Images | Inline preview modal |
| PDF | Opens in a new tab |
| Text files | Inline preview when under `preview.text_limit_bytes` |
| Other files | Metadata view |

---

## Testing

Run the package checks locally with:

```bash
composer test
composer analyse
composer format
```

The automated test suite uses Pest, Orchestra Testbench, and `Storage::fake()`
to cover:

- listing and nested directories
- folder creation
- file uploads
- renames and moves
- deletes
- root restriction safety
- temporary URLs
- preview modes
- unsupported filesystem features
- authorization fallback and policies

---

## Extending

You can adapt the package by:

- adding more disks to the browser config
- scoping each disk to a specific root prefix
- adjusting preview limits and upload size limits
- implementing policies for `BrowserTarget`
- reusing the service layer in custom package integrations

The core browser logic lives in
`MrAdder\FilamentS3Browser\Services\FilesystemBrowserService`, while previews
and authorization are handled by dedicated services.

---

## Screenshots

Screenshot placeholders can be added under `docs/images/` as the package UI
evolves.

---

## Roadmap

Planned improvements:

- drag and drop uploads
- bulk selection actions
- richer preview support for audio and video files
- optional visibility editing UI
- dashboard or widget integration for custom Filament experiences

---

## Contributing

Pull requests are welcome.

Please read `CONTRIBUTING.md` before submitting changes.

---

## Security

If you discover a security issue, please review `SECURITY.md`.

---

## License

MIT License. See `LICENSE.md`.

---

## Support the Project

If this plugin saves you time, consider giving it a GitHub star. It helps other
Filament and Laravel developers discover the project.
