<?php

namespace App\Services;

use App\Models\IdempotencyKey;
use App\Models\User;
use Closure;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

class IdempotencyService
{
    /**
     * Execute a write at most once per (user, key) within the TTL.
     *
     * The key row is inserted in the same transaction as the write, so either
     * both are committed or neither is. Two concurrent requests with the same
     * key race on the unique index; the loser rolls back and replays the
     * winner's stored response.
     *
     * @param  Closure(): array{status: int, body: array}  $operation
     * @return array{status: int, body: array, replayed: bool}
     */
    public function run(User $user, string $key, string $fingerprint, Closure $operation): array
    {
        try {
            return DB::transaction(function () use ($user, $key, $fingerprint, $operation) {
                $existing = $this->findForUpdate($user, $key);

                if ($existing && $existing->expires_at->isPast()) {
                    $existing->delete();
                    $existing = null;
                }

                if ($existing) {
                    return $this->replay($existing, $fingerprint);
                }

                $result = $operation();

                IdempotencyKey::create([
                    'key' => $key,
                    'user_id' => $user->id,
                    'request_hash' => $fingerprint,
                    'response_status' => $result['status'],
                    'response_body' => $result['body'],
                    'expires_at' => now()->addHours(config('servicedesk.idempotency.ttl_hours')),
                ]);

                return [...$result, 'replayed' => false];
            });
        } catch (UniqueConstraintViolationException $exception) {
            $existing = IdempotencyKey::query()
                ->where('user_id', $user->id)
                ->where('key', $key)
                ->first();

            if (! $existing) {
                throw $exception;
            }

            return $this->replay($existing, $fingerprint);
        }
    }

    public function purgeExpired(): int
    {
        return IdempotencyKey::query()->where('expires_at', '<=', now())->delete();
    }

    private function findForUpdate(User $user, string $key): ?IdempotencyKey
    {
        return IdempotencyKey::query()
            ->where('user_id', $user->id)
            ->where('key', $key)
            ->lockForUpdate()
            ->first();
    }

    /**
     * @return array{status: int, body: array, replayed: bool}
     */
    private function replay(IdempotencyKey $record, string $fingerprint): array
    {
        if (! hash_equals($record->request_hash, $fingerprint)) {
            throw new ConflictHttpException('This Idempotency-Key was already used with a different request payload.');
        }

        return [
            'status' => $record->response_status,
            'body' => $record->response_body,
            'replayed' => true,
        ];
    }
}
