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
it('accepts a Word or Excel Open XML file only when its internal structure matches', function (string $extension, string $mime, string $entry): void {
    $path = tempnam(sys_get_temp_dir(), 'document-office-');
    $zip = new ZipArchive;
    expect($zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE))->toBeTrue();
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString($entry, '<document/>');
    $zip->close();
    try {
        $file = new UploadedFile($path, 'test.'.$extension, null, null, true);
        expect(app(DocumentFilePolicy::class)->validate($file, [$mime], 10240))->toBe($mime);
    } finally {
        @unlink($path);
    }
})->with([
    ['docx', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'word/document.xml'],
    ['xlsx', 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet', 'xl/workbook.xml'],
]);

it('rejects an Excel archive renamed as a Word document', function (): void {
    $path = tempnam(sys_get_temp_dir(), 'document-office-');
    $zip = new ZipArchive;
    $zip->open($path, ZipArchive::CREATE | ZipArchive::OVERWRITE);
    $zip->addFromString('[Content_Types].xml', '<Types/>');
    $zip->addFromString('xl/workbook.xml', '<workbook/>');
    $zip->close();
    try {
        $file = new UploadedFile($path, 'fake.docx', null, null, true);
        expect(fn () => app(DocumentFilePolicy::class)->validate($file, ['application/vnd.openxmlformats-officedocument.wordprocessingml.document'], 10240))
            ->toThrow(ValidationException::class);
    } finally {
        @unlink($path);
    }
});
