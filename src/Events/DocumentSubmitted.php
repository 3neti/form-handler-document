<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Events;

use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;

final readonly class DocumentSubmitted
{
    public function __construct(public string $flowId, public DocumentSubmission $submission) {}
}
