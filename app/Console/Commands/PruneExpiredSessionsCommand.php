<?php

namespace App\Console\Commands;

use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Contracts\Session\Session;

#[Signature('app:prune-expired-sessions')]
#[Description('Garbage collect expired rows from the configured session store')]
class PruneExpiredSessionsCommand extends Command
{
    public function handle(Session $session): int
    {
        $lifetimeSeconds = (int) config('session.lifetime') * 60;

        $session->getHandler()->gc($lifetimeSeconds);

        $this->info('Expired sessions pruned.');

        return self::SUCCESS;
    }
}
