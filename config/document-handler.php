<?php

return [
    'disk' => env('FORM_DOCUMENT_DISK', 'local'),
    'max_items' => (int) env('FORM_DOCUMENT_MAX_ITEMS', 5),
    'max_file_kb' => (int) env('FORM_DOCUMENT_MAX_FILE_KB', 10240),
    'staged_retention_hours' => (int) env('FORM_DOCUMENT_STAGED_RETENTION_HOURS', 24),
];
