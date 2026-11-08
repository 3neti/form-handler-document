<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Services;

use Illuminate\Support\Facades\DB;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;

final class PruneStagedDocuments
{
    public function prune(): int
    {
        $cutoff = now()->subHours(max(1, (int) config('document-handler.staged_retention_hours', 24)));
        $removed = 0;
        DocumentSubmission::query()->whereIn('status', ['staged', 'expired'])
            ->where('updated_at', '<=', $cutoff)->orderBy('id')->chunkById(100, function ($submissions) use ($cutoff, &$removed): void {
                foreach ($submissions as $candidate) {
                    $submission = DB::transaction(function () use ($candidate, $cutoff): ?DocumentSubmission {
                        $record = DocumentSubmission::query()->lockForUpdate()->find($candidate->getKey());
                        if ($record === null || ! in_array($record->status, ['staged', 'expired'], true)
                            || $record->updated_at->gt($cutoff)) {
                            return null;
                        }
                        $record->forceFill(['status' => 'expired'])->save();

                        return $record;
                    });
                    if ($submission !== null) {
                        $submission->delete();
                        $removed++;
                    }
                }
            });

        return $removed;
    }
}
