<?php

namespace App\Http\Middleware;

class PulseBasicAuth extends BasicAuth
{
    protected function configNamespace(): string
    {
        return 'pulse';
    }

    protected function realm(): string
    {
        return 'Pulse';
    }
}
