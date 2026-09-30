<?php

namespace App\Console\Commands;

use App\Services\IdempotencyService;
use Illuminate\Console\Command;

class PurgeExpiredIdempotencyKeys extends Command
{
    protected $signature = 'idempotency:purge';

    protected $description = 'Delete idempotency keys older than their TTL';

    public function handle(IdempotencyService $idempotency): int
    {
        $this->info("Purged {$idempotency->purgeExpired()} expired idempotency keys.");

        return self::SUCCESS;
    }
}
