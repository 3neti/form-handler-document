<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('form_flow_document_scopes', function (Blueprint $table): void {
            $table->id();
            $table->char('scope_hash', 64)->unique();
            $table->timestamps();
        });
        Schema::create('form_flow_document_submissions', function (Blueprint $table): void {
            $table->id();
            $table->ulid('reference')->unique();
            $table->char('flow_hash', 64)->index();
            $table->char('participant_hash', 64)->index();
            $table->string('document_key', 64);
            $table->string('mime_type', 127);
            $table->unsignedBigInteger('size');
            $table->char('sha256', 64);
            $table->string('status', 24)->default('staged');
            $table->timestamp('linked_at')->nullable();
            $table->timestamps();
            $table->index(['flow_hash', 'participant_hash', 'document_key']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('form_flow_document_submissions');
        Schema::dropIfExists('form_flow_document_scopes');
    }
};
