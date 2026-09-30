<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Resources\AuditLogResource;
use App\Models\Ticket;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class TicketActivityController extends Controller
{
    /**
     * Activity timeline for the agent ticket view, built from the audit log.
     */
    public function index(Ticket $ticket): AnonymousResourceCollection
    {
        $this->authorize('viewActivity', $ticket);

        return AuditLogResource::collection(
            $ticket->auditLogs()->with('user')->latest()->latest('id')->paginate(50)
        );
    }
}
