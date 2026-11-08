<?php

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;
use LBHurtado\FormHandlerDocument\Services\DocumentFilePolicy;

it('accepts an allowed PDF with a matching extension and signature', function (): void {
    $file = UploadedFile::fake()->createWithContent('statement.pdf', "%PDF-1.4\n% sample\n");
    expect(app(DocumentFilePolicy::class)->validate($file, ['application/pdf'], 10240))->toBe('application/pdf');
});

it('rejects a misleading extension even when the instruction allows PDFs', function (): void {
    $file = UploadedFile::fake()->createWithContent('statement.pdf', 'plain text is not a PDF');
    app(DocumentFilePolicy::class)->validate($file, ['application/pdf'], 10240);
})->throws(ValidationException::class);

it('rejects a real PDF when its type is not requested', function (): void {
    $file = UploadedFile::fake()->createWithContent('statement.pdf', "%PDF-1.4\n% sample\n");
    app(DocumentFilePolicy::class)->validate($file, ['image/png'], 10240);
})->throws(ValidationException::class);

it('accepts CSV detected as plain text only with the CSV extension', function (): void {
    $file = UploadedFile::fake()->createWithContent('report.csv', "date,amount\n2026-01-01,100\n");
    expect(app(DocumentFilePolicy::class)->validate($file, ['text/csv'], 10240))->toBe('text/csv');
});
