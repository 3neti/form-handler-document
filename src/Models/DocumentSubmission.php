<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Models;

use Illuminate\Database\Eloquent\Model;
use Spatie\MediaLibrary\HasMedia;
use Spatie\MediaLibrary\InteractsWithMedia;

final class DocumentSubmission extends Model implements HasMedia
{
    use InteractsWithMedia;

    protected $table = 'form_flow_document_submissions';

    protected $guarded = ['*'];

    protected $hidden = ['flow_hash', 'participant_hash', 'sha256'];

    public function registerMediaCollections(): void
    {
        $this->addMediaCollection('documents')->useDisk((string) config('document-handler.disk', 'local'));
    }
}
