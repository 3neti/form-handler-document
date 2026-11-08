<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument;

use Illuminate\Support\ServiceProvider;
use LBHurtado\FormFlowManager\Services\FormFlowPresentationResolver;
use LBHurtado\FormHandlerDocument\Contracts\DocumentAccess;
use LBHurtado\FormHandlerDocument\Services\DenyDocumentAccess;

final class DocumentHandlerServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/document-handler.php', 'document-handler');
        $this->app->bindIf(DocumentAccess::class, DenyDocumentAccess::class);
    }

    public function boot(): void
    {
        $this->loadMigrationsFrom(__DIR__.'/../database/migrations');
        $this->loadRoutesFrom(__DIR__.'/../routes/web.php');
        $this->publishes([__DIR__.'/../stubs/resources/js/pages/form-flow/documents' => resource_path('js/pages/form-flow/documents')], 'document-handler-stubs');
        $handlers = config('form-flow.handlers', []);
        $handlers['documents'] = DocumentHandler::class;
        config(['form-flow.handlers' => $handlers]);
        app(FormFlowPresentationResolver::class)->registerScreens(['documents']);
    }
}
