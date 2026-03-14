<?php

declare(strict_types=1);

namespace MrAdder\FilamentS3Browser\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use MrAdder\FilamentS3Browser\Services\BrowserAuthorizationService;
use MrAdder\FilamentS3Browser\Services\FilesystemBrowserService;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class PreviewFileController extends Controller
{
    public function __invoke(
        Request $request,
        FilesystemBrowserService $browser,
        BrowserAuthorizationService $authorization,
    ): StreamedResponse {
        $disk = (string) $request->query('disk', '');
        $path = (string) $request->query('path', '');

        abort_unless(
            $authorization->canView($request->user(), $disk, $path, false),
            403,
        );

        return $browser->previewResponse($disk, $path);
    }
}
