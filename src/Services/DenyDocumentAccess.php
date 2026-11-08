<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Services;

use Illuminate\Http\Request;
use LBHurtado\FormHandlerDocument\Contracts\DocumentAccess;
use LBHurtado\FormHandlerDocument\Data\DocumentUploadContext;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;

final class DenyDocumentAccess implements DocumentAccess
{
    public function authorizeUpload(Request $request, string $flowId, string $documentKey): DocumentUploadContext
    {
        abort(404);
    }

    public function authorizeRead(Request $request, DocumentSubmission $submission): void
    {
        abort(404);
    }
}
