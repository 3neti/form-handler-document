<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use LBHurtado\FormHandlerDocument\Data\DocumentUploadContext;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use LogicException;
use Throwable;

final readonly class StoreDocumentSubmission
{
    public function __construct(private DocumentFilePolicy $filePolicy) {}

    public function store(UploadedFile $file, DocumentUploadContext $context): DocumentSubmission
    {
        if (DB::transactionLevel() !== 0) {
            throw new LogicException('Document staging must start outside an existing transaction.');
        }
        if (! preg_match('/\A[a-f0-9]{64}\z/', $context->flowHash)
            || ! preg_match('/\A[a-f0-9]{64}\z/', $context->participantHash)
            || ! preg_match('/\A[a-zA-Z0-9_-]{1,64}\z/', $context->documentKey)) {
            throw new LogicException('Invalid document upload scope.');
        }
        $disk = (string) config('document-handler.disk', 'local');
        if ($disk === '' || config("filesystems.disks.{$disk}") === null
            || config("filesystems.disks.{$disk}.visibility") === 'public') {
            throw new LogicException('Document uploads require a private disk.');
        }
        $mime = $this->filePolicy->validate($file, $context->acceptedMimeTypes, max(1, (int) config('document-handler.max_file_kb', 10240)));
        $sha256 = hash_file('sha256', $file->getPathname());
        if (! is_string($sha256)) {
            throw new LogicException('Document could not be hashed.');
        }
        $size = $file->getSize();
        $extension = strtolower($file->getClientOriginalExtension());
        $scope = hash('sha256', $context->flowHash.':'.$context->participantHash.':'.$context->documentKey);
        $storedPath = null;

        try {
            return DB::transaction(function () use ($file, $context, $disk, $mime, $size, $sha256, $extension, $scope, &$storedPath): DocumentSubmission {
                DB::table('form_flow_document_scopes')->insertOrIgnore(['scope_hash' => $scope, 'created_at' => now(), 'updated_at' => now()]);
                DB::table('form_flow_document_scopes')->where('scope_hash', $scope)->lockForUpdate()->first();
                $existing = DocumentSubmission::query()->where('flow_hash', $context->flowHash)
                    ->where('participant_hash', $context->participantHash)->where('document_key', $context->documentKey)
                    ->where('sha256', $sha256)->whereIn('status', ['staged', 'linked'])->latest()->first();
                if ($existing !== null && $existing->hasMedia('documents')) {
                    return $existing;
                }

                $submission = new DocumentSubmission;
                $submission->forceFill([
                    'reference' => (string) Str::ulid(),
                    'flow_hash' => $context->flowHash, 'participant_hash' => $context->participantHash,
                    'document_key' => $context->documentKey, 'mime_type' => $mime,
                    'size' => $size, 'sha256' => $sha256, 'status' => 'staged',
                ])->save();
                $media = $submission->addMedia($file)->usingFileName(Str::uuid().'.'.$extension)->toMediaCollection('documents', $disk);
                $storedPath = $media->getPathRelativeToRoot();

                return $submission;
            });
        } catch (Throwable $exception) {
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk($disk)->delete($storedPath);
            }
            throw $exception;
        }
    }
}
