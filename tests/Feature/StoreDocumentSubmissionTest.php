<?php

use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormFlowManager\Data\FormFlowStepData;
use LBHurtado\FormHandlerDocument\Contracts\DocumentAccess;
use LBHurtado\FormHandlerDocument\Data\DocumentUploadContext;
use LBHurtado\FormHandlerDocument\DocumentHandler;
use LBHurtado\FormHandlerDocument\Models\DocumentSubmission;
use LBHurtado\FormHandlerDocument\Services\StoreDocumentSubmission;

beforeEach(function (): void {
    (require __DIR__.'/../../vendor/spatie/laravel-medialibrary/database/migrations/create_media_table.php.stub')->up();
    (require __DIR__.'/../../database/migrations/2026_11_08_000001_create_form_flow_document_submissions.php')->up();
    config()->set('document-handler.disk', 'private-documents');
    config()->set('filesystems.disks.private-documents', ['driver' => 'local', 'root' => storage_path('app/private-documents'), 'visibility' => 'private']);
    Storage::fake('private-documents');
});

it('stores one private media file and reuses identical uploads in the same scope', function (): void {
    $scope = new DocumentUploadContext(str_repeat('a', 64), str_repeat('b', 64), 'id', ['application/pdf']);
    $contents = "%PDF-1.4\n% sample\n";
    $service = app(StoreDocumentSubmission::class);
    $first = $service->store(UploadedFile::fake()->createWithContent('id.pdf', $contents), $scope);
    $again = $service->store(UploadedFile::fake()->createWithContent('id.pdf', $contents), $scope);

    expect($again->id)->toBe($first->id)->and($first->getMedia('documents'))->toHaveCount(1)
        ->and($first->getFirstMedia('documents')->disk)->toBe('private-documents');
    Storage::disk('private-documents')->assertExists($first->getFirstMedia('documents')->getPathRelativeToRoot());
});

it('does not reuse an upload from a different participant', function (): void {
    $service = app(StoreDocumentSubmission::class);
    $contents = "%PDF-1.4\n% sample\n";
    $first = $service->store(UploadedFile::fake()->createWithContent('id.pdf', $contents), new DocumentUploadContext(str_repeat('a', 64), str_repeat('b', 64), 'id', ['application/pdf']));
    $other = $service->store(UploadedFile::fake()->createWithContent('id.pdf', $contents), new DocumentUploadContext(str_repeat('a', 64), str_repeat('c', 64), 'id', ['application/pdf']));
    expect($other->id)->not->toBe($first->id);
});

it('requires all required files before advancing', function (): void {
    $step = new FormFlowStepData('documents', ['documents' => [['key' => 'id', 'label' => 'ID', 'accept' => ['application/pdf'], 'required' => true]]]);
    app(DocumentHandler::class)->handle(Request::create('/', 'POST', ['data' => ['documents' => []]]), $step, ['flow_id' => 'flow-1']);
})->throws(ValidationException::class);

it('accepts only a stored submission belonging to the authorized flow and participant', function (): void {
    $scope = new DocumentUploadContext(str_repeat('a', 64), str_repeat('b', 64), 'id', ['application/pdf']);
    $submission = app(StoreDocumentSubmission::class)->store(UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% sample\n"), $scope);
    app()->instance(DocumentAccess::class, new class($scope) implements DocumentAccess
    {
        public function __construct(private DocumentUploadContext $scope) {}

        public function authorizeUpload(Request $request, string $flowId, string $documentKey): DocumentUploadContext
        {
            return $this->scope;
        }

        public function authorizeRead(Request $request, DocumentSubmission $submission): void {}
    });
    $step = new FormFlowStepData('documents', ['documents' => [['key' => 'id', 'label' => 'ID', 'accept' => ['application/pdf'], 'required' => true]]]);
    $request = Request::create('/', 'POST', ['data' => ['documents' => [['key' => 'id', 'reference' => $submission->reference]]]]);
    expect(app(DocumentHandler::class)->handle($request, $step, ['flow_id' => 'flow-1']))->toBe(['documents' => [['key' => 'id', 'reference' => $submission->reference]]]);
    $submission->forceFill(['participant_hash' => str_repeat('c', 64)])->save();
    expect(fn () => app(DocumentHandler::class)->handle($request, $step, ['flow_id' => 'flow-1']))->toThrow(ValidationException::class);
});

it('denies uploads when the host has not bound an authorization implementation', function (): void {
    $this->post('/form-flow/flow-1/documents/id')->assertNotFound();
});

it('rejects a public document disk', function (): void {
    config()->set('filesystems.disks.private-documents.visibility', 'public');
    $scope = new DocumentUploadContext(str_repeat('a', 64), str_repeat('b', 64), 'id', ['application/pdf']);
    app(StoreDocumentSubmission::class)->store(UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% sample\n"), $scope);
})->throws(LogicException::class);

it('authorizes private document viewing through the host access contract', function (): void {
    $scope = new DocumentUploadContext(str_repeat('a', 64), str_repeat('b', 64), 'id', ['application/pdf']);
    $submission = app(StoreDocumentSubmission::class)->store(UploadedFile::fake()->createWithContent('id.pdf', "%PDF-1.4\n% sample\n"), $scope);
    app()->instance(DocumentAccess::class, new class($scope) implements DocumentAccess
    {
        public function __construct(private DocumentUploadContext $scope) {}

        public function authorizeUpload(Request $request, string $flowId, string $documentKey): DocumentUploadContext
        {
            return $this->scope;
        }

        public function authorizeRead(Request $request, DocumentSubmission $submission): void
        {
            abort_unless($request->header('X-Test-Participant') === 'authorized', 404);
        }
    });
    $url = route('form-flow.documents.show', ['reference' => $submission->reference]);
    $this->get($url)->assertNotFound();
    $this->withHeader('X-Test-Participant', 'authorized')->get($url)->assertOk()
        ->assertHeader('Cache-Control', 'no-store, private')
        ->assertHeader('X-Content-Type-Options', 'nosniff');
});
