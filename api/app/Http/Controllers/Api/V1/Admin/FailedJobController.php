<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use App\Http\Resources\OutboxEventResource;
use App\Models\OutboxEvent;
use App\Services\OutboxService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;

/**
 * Dead-letter inspection: Laravel's failed_jobs table plus outbox events
 * that exhausted their retries.
 */
class FailedJobController extends Controller
{
    public function __construct(
        private readonly OutboxService $outbox,
    ) {}

    public function index(Request $request): JsonResponse
    {
        $jobs = DB::table('failed_jobs')
            ->select(['id', 'uuid', 'connection', 'queue', 'payload', 'exception', 'failed_at'])
            ->orderByDesc('failed_at')
            ->paginate(min(100, max(1, $request->integer('per_page', 25))));

        $jobs->getCollection()->transform(fn ($job) => [
            'id' => $job->id,
            'uuid' => $job->uuid,
            'queue' => $job->queue,
            'job' => json_decode($job->payload, true)['displayName'] ?? null,
            'exception' => mb_substr($job->exception, 0, 1000),
            'failed_at' => $job->failed_at,
        ]);

        return response()->json($jobs);
    }

    public function retry(string $uuid): JsonResponse
    {
        abort_unless(DB::table('failed_jobs')->where('uuid', $uuid)->exists(), 404, 'Failed job not found.');

        Artisan::call('queue:retry', ['id' => [$uuid]]);

        return response()->json(['message' => 'Job pushed back onto the queue.']);
    }

    public function outboxEvents(Request $request): AnonymousResourceCollection
    {
        $status = $request->validate(['status' => ['nullable', 'in:failed,pending,processed']])['status'] ?? 'failed';

        $events = OutboxEvent::query()
            ->when($status === 'failed', fn ($q) => $q->failed())
            ->when($status === 'pending', fn ($q) => $q->pending())
            ->when($status === 'processed', fn ($q) => $q->whereNotNull('processed_at'))
            ->latest('id')
            ->paginate(25)
            ->withQueryString();

        return OutboxEventResource::collection($events);
    }

    public function retryOutboxEvent(OutboxEvent $outboxEvent): JsonResponse
    {
        if ($outboxEvent->isProcessed()) {
            throw new ConflictHttpException('This event has already been processed.');
        }

        $this->outbox->retry($outboxEvent);

        return response()->json(['message' => 'Outbox event re-queued.']);
    }
}
