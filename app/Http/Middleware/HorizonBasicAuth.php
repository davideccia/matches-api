<?php

namespace App\Http\Middleware;

class HorizonBasicAuth extends BasicAuth
{
    protected function configNamespace(): string
    {
        return 'horizon';
    }

    protected function realm(): string
    {
        return 'Horizon';
    }
}
