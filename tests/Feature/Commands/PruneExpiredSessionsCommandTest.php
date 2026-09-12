<?php

namespace Tests\Feature\Commands;

use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class PruneExpiredSessionsCommandTest extends TestCase
{
    public function test_command_deletes_only_expired_session_rows(): void
    {
        // phpunit.xml forces SESSION_DRIVER=array for HTTP tests; this command needs the
        // database-backed driver actually configured for this scenario.
        config(['session.driver' => 'database', 'session.lifetime' => 120]);
        $this->app->forgetInstance('session.store');
        $this->app->forgetInstance('session');

        $lifetimeSeconds = 120 * 60;

        DB::table('sessions')->insert([
            'id' => 'expired-session',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->subSeconds($lifetimeSeconds + 60)->timestamp,
        ]);

        DB::table('sessions')->insert([
            'id' => 'fresh-session',
            'user_id' => null,
            'ip_address' => '127.0.0.1',
            'user_agent' => 'test',
            'payload' => base64_encode(serialize([])),
            'last_activity' => now()->timestamp,
        ]);

        $this->artisan('app:prune-expired-sessions')->assertExitCode(0);

        $this->assertDatabaseMissing('sessions', ['id' => 'expired-session']);
        $this->assertDatabaseHas('sessions', ['id' => 'fresh-session']);
    }
}
