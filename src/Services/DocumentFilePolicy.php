<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Services;

use Illuminate\Http\UploadedFile;
use Illuminate\Validation\ValidationException;

final class DocumentFilePolicy
{
    private const TYPES = [
        'pdf' => 'application/pdf',
        'doc' => 'application/msword',
        'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'xls' => 'application/vnd.ms-excel',
        'xlsx' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'csv' => 'text/csv',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'png' => 'image/png',
    ];

    /** @param list<string> $accepted */
    public function validate(UploadedFile $file, array $accepted, int $maxKilobytes): string
    {
        $extension = strtolower($file->getClientOriginalExtension());
        $expected = self::TYPES[$extension] ?? null;
        $size = $file->getSize();
        if (! $file->isValid() || ! is_string($expected) || ! in_array($expected, $accepted, true)
            || ! is_int($size) || $size <= 0 || $size > $maxKilobytes * 1024) {
            throw ValidationException::withMessages(['file' => 'The file type or size is not allowed.']);
        }

        $detected = (new \finfo(FILEINFO_MIME_TYPE))->file($file->getPathname());
        $isCsv = $expected === 'text/csv' && in_array($detected, ['text/plain', 'text/csv'], true)
            && $this->isTextCsv($file->getPathname());
        if ($detected !== $expected && ! $isCsv) {
            throw ValidationException::withMessages(['file' => 'The file content does not match the selected type.']);
        }

        return $expected;
    }

    private function isTextCsv(string $path): bool
    {
        $content = file_get_contents($path);

        return is_string($content) && ! str_contains($content, "\0") && mb_check_encoding($content, 'UTF-8');
    }
}
