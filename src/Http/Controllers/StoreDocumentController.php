<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use LBHurtado\FormHandlerDocument\Contracts\DocumentAccess;
use LBHurtado\FormHandlerDocument\Services\StoreDocumentSubmission;

final class StoreDocumentController extends Controller
{
    public function __invoke(Request $request, string $flowId, string $key, DocumentAccess $access, StoreDocumentSubmission $store): JsonResponse
    {
        $context = $access->authorizeUpload($request, $flowId, $key);
        abort_unless(hash_equals($context->documentKey, $key), 404);
        $validated = $request->validate(['file' => ['required', 'file']]);
        $submission = $store->store($validated['file'], $context);

        return response()->json(['reference' => $submission->reference, 'key' => $submission->document_key, 'status' => $submission->status], 201)
            ->header('Cache-Control', 'no-store, private');
    }
}
