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
        $isOfficeXml = in_array($extension, ['docx', 'xlsx'], true)
            && in_array($detected, ['application/zip', $expected], true)
            && $this->isOfficeXml($file->getPathname(), $extension);
        $isOfficeBinary = in_array($extension, ['doc', 'xls'], true)
            && in_array($detected, ['application/x-ole-storage', 'application/CDFV2', $expected], true)
            && $this->isOfficeBinary($file->getPathname(), $extension);
        if ((in_array($extension, ['docx', 'xlsx'], true) && ! $isOfficeXml)
            || (in_array($extension, ['doc', 'xls'], true) && ! $isOfficeBinary)
            || ($detected !== $expected && ! $isCsv && ! $isOfficeXml && ! $isOfficeBinary)) {
            throw ValidationException::withMessages(['file' => 'The file content does not match the selected type.']);
        }

        return $expected;
    }

    private function isOfficeXml(string $path, string $extension): bool
    {
        if (! class_exists(\ZipArchive::class)) {
            return false;
        }
        $zip = new \ZipArchive;
        if ($zip->open($path) !== true) {
            return false;
        }
        try {
            $required = $extension === 'docx' ? 'word/document.xml' : 'xl/workbook.xml';

            return $zip->locateName('[Content_Types].xml') !== false && $zip->locateName($required) !== false;
        } finally {
            $zip->close();
        }
    }

    private function isOfficeBinary(string $path, string $extension): bool
    {
        $content = file_get_contents($path);
        if (! is_string($content) || ! str_starts_with($content, "\xD0\xCF\x11\xE0\xA1\xB1\x1A\xE1")) {
            return false;
        }
        $names = $extension === 'doc' ? ['WordDocument'] : ['Workbook', 'Book'];
        foreach ($names as $name) {
            if (str_contains($content, mb_convert_encoding($name, 'UTF-16LE', 'UTF-8'))) {
                return true;
            }
        }

        return false;
    }

    private function isTextCsv(string $path): bool
    {
        $content = file_get_contents($path);

        return is_string($content) && ! str_contains($content, "\0") && mb_check_encoding($content, 'UTF-8');
    }
}
