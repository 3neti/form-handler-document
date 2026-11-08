<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use LBHurtado\FormFlowManager\Contracts\FormHandlerInterface;
use LBHurtado\FormFlowManager\Contracts\FormHandlerPreviewInterface;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerDocument\Contracts\DocumentAccess;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;

final class DocumentHandler implements FormHandlerInterface, FormHandlerPreviewInterface
{
    public function getName(): string
    {
        return 'documents';
    }

    public function handle(Request $request, FormFlowStepData $step, array $context = []): array
    {
        $data = $request->input('data', []);
        $provided = is_array($data) ? ($data['documents'] ?? []) : [];
        $definitions = $step->config['documents'] ?? [];
        $flowId = $context['flow_id'] ?? null;
        if (! is_string($flowId) || $flowId === '' || ! is_array($provided) || ! array_is_list($provided)
            || ! is_array($definitions) || ! array_is_list($definitions) || count($definitions) > max(1, (int) config('document-handler.max_items', 5))) {
            throw ValidationException::withMessages(['documents' => 'The document step is invalid.']);
        }
        $byKey = [];
        foreach ($provided as $item) {
            if (! is_array($item) || ! is_string($item['key'] ?? null) || ! is_string($item['reference'] ?? null)
                || isset($byKey[$item['key']])) {
                throw ValidationException::withMessages(['documents' => 'The document references are invalid.']);
            }
            $byKey[$item['key']] = $item['reference'];
        }
        $result = [];
        foreach ($definitions as $definition) {
            if (! is_array($definition) || ! is_string($definition['key'] ?? null) || ! is_bool($definition['required'] ?? null)) {
                throw ValidationException::withMessages(['documents' => 'The document configuration is invalid.']);
            }
            $key = $definition['key'];
            $reference = $byKey[$key] ?? null;
            if ($reference === null) {
                if ($definition['required']) {
                    throw ValidationException::withMessages(['documents' => 'Upload all required documents before continuing.']);
                }

                continue;
            }
            $scope = app(DocumentAccess::class)->authorizeUpload($request, $flowId, $key);
            $submission = DocumentSubmission::query()->where('reference', $reference)->where('flow_hash', $scope->flowHash)
                ->where('participant_hash', $scope->participantHash)->where('document_key', $key)->whereIn('status', ['staged', 'linked'])->first();
            $media = $submission?->getFirstMedia('documents');
            if ($submission === null || $media === null || ! Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())
                || ! in_array($submission->mime_type, $scope->acceptedMimeTypes, true)) {
                throw ValidationException::withMessages(['documents' => 'A document is missing or unavailable. Please upload it again.']);
            }
            $result[] = ['key' => $key, 'reference' => $reference];
            unset($byKey[$key]);
        }
        if ($byKey !== []) {
            throw ValidationException::withMessages(['documents' => 'An unexpected document was submitted.']);
        }

        return ['documents' => $result];
    }

    public function validate(array $data, array $rules): bool
    {
        return true;
    }

    public function render(FormFlowStepData $step, array $context = [])
    {
        return Inertia::render('form-flow/documents/DocumentCapturePage', $this->props($step, $context));
    }

    public function preview(FormFlowStepData $step, array $context = []): array
    {
        return ['component' => 'form-flow/documents/DocumentCapturePage', 'props' => $this->props($step, [...$context, 'preview_mode' => true])];
    }

    public function getConfigSchema(): array
    {
        return ['documents' => 'required|array|max:'.max(1, (int) config('document-handler.max_items', 5))];
    }

    private function props(FormFlowStepData $step, array $context): array
    {
        $flowId = $context['flow_id'] ?? null;
        $stepIndex = (string) ($context['step_index'] ?? 0);
        $preview = (bool) ($context['preview_mode'] ?? false);
        $existing = [];
        if (! $preview && is_string($flowId)) {
            foreach ((array) ($step->config['documents'] ?? []) as $definition) {
                $key = is_array($definition) ? ($definition['key'] ?? null) : null;
                if (! is_string($key)) {
                    continue;
                }
                $scope = app(DocumentAccess::class)->authorizeUpload(request(), $flowId, $key);
                $submission = DocumentSubmission::query()->where('flow_hash', $scope->flowHash)
                    ->where('participant_hash', $scope->participantHash)->where('document_key', $key)
                    ->whereIn('status', ['staged', 'linked'])->latest()->first();
                $media = $submission?->getFirstMedia('documents');
                if ($submission !== null && $media !== null && Storage::disk($media->disk)->exists($media->getPathRelativeToRoot())
                    && in_array($submission->mime_type, $scope->acceptedMimeTypes, true)) {
                    $existing[] = ['key' => $key, 'reference' => $submission->reference];
                }
            }
        }

        return [
            'documents' => $step->config['documents'] ?? [],
            'existing_documents' => $existing,
            'preview_mode' => $preview,
            'upload_url_template' => ! $preview && is_string($flowId) ? route('form-flow.documents.store', ['flowId' => $flowId, 'key' => 'DOCUMENT_KEY']) : null,
            'submit_url' => ! $preview && is_string($flowId) ? route('form-flow.update-step', ['flow_id' => $flowId, 'step' => $stepIndex]) : null,
            'presentation' => $step->config['presentation'] ?? null,
        ];
    }
}
