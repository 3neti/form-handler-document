<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Data;

final readonly class DocumentUploadContext
{
    /** @param list<string> $acceptedMimeTypes */
    public function __construct(
        public string $flowHash,
        public string $participantHash,
        public string $documentKey,
        public array $acceptedMimeTypes,
    ) {}
}
