<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\UploadAttachmentsRequest;
use App\Http\Resources\AttachmentResource;
use App\Models\Attachment;
use App\Models\Ticket;
use App\Services\AttachmentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

class TicketAttachmentController extends Controller
{
    public function __construct(
        private readonly AttachmentService $attachmentService,
    ) {}

    public function store(UploadAttachmentsRequest $request, Ticket $ticket): JsonResponse
    {
        $this->authorize('uploadAttachment', $ticket);

        try {
            $attachments = DB::transaction(fn () => $this->attachmentService->store(
                $ticket,
                $request->user(),
                $request->file('attachments'),
            ));
        } catch (Throwable $exception) {
            $this->attachmentService->discardWrittenFiles();

            throw $exception;
        }

        return AttachmentResource::collection(collect($attachments)->each->load('user'))
            ->response()
            ->setStatusCode(201);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        $this->authorize('view', $attachment);

        return $this->attachmentService->download($attachment);
    }
}
