<?php

declare(strict_types=1);

namespace LBHurtado\FormHandlerDocument\Tests;

use LBHurtado\FormFlowManager\FormFlowServiceProvider;
use LBHurtado\FormHandlerDocument\DocumentHandlerServiceProvider;
use Orchestra\Testbench\TestCase as Orchestra;
use Spatie\MediaLibrary\MediaLibraryServiceProvider;

abstract class TestCase extends Orchestra
{
    protected function defineEnvironment($app): void
    {
        $app['config']->set('app.key', 'base64:'.base64_encode(str_repeat('a', 32)));
    }

    protected function getPackageProviders($app): array
    {
        return [MediaLibraryServiceProvider::class, FormFlowServiceProvider::class, DocumentHandlerServiceProvider::class];
    }
}
