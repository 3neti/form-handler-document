<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Contracts;

use Illuminate\Http\Request;
use LBHurtado\FormHandlerDocument\Data\DocumentUploadContext;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;

interface DocumentAccess
{
    public function authorizeUpload(Request $request, string $flowId, string $documentKey): DocumentUploadContext;

    public function authorizeRead(Request $request, DocumentSubmission $submission): void;
}
