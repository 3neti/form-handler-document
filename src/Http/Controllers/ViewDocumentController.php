<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\Storage;
use LBHurtado\FormHandlerDocument\Contracts\DocumentAccess;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use Symfony\Component\HttpFoundation\StreamedResponse;

final class ViewDocumentController extends Controller
{
    public function __invoke(Request $request, string $reference, DocumentAccess $access): StreamedResponse
    {
        $submission = DocumentSubmission::query()->where('reference', $reference)->firstOrFail();
        $access->authorizeRead($request, $submission);
        $media = $submission->getFirstMedia('documents');
        abort_unless($media !== null, 404);
        $path = $media->getPathRelativeToRoot();
        abort_unless(Storage::disk($media->disk)->exists($path), 404);
        $inline = in_array($submission->mime_type, ['application/pdf', 'image/png', 'image/jpeg'], true);

        return Storage::disk($media->disk)->response($path, $media->file_name, [
            'Content-Type' => $submission->mime_type,
            'Cache-Control' => 'no-store, private',
            'X-Content-Type-Options' => 'nosniff',
            'Content-Security-Policy' => "default-src 'none'; sandbox",
        ], $inline ? 'inline' : 'attachment');
    }
}
