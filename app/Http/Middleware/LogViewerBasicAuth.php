<?php

namespace App\Http\Middleware;

class LogViewerBasicAuth extends BasicAuth
{
    protected function configNamespace(): string
    {
        return 'log-viewer';
    }

    protected function realm(): string
    {
        return 'Log Viewer';
    }
}
