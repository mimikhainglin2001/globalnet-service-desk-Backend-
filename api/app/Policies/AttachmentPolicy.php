<?php

namespace App\Policies;

use App\Models\Attachment;
use App\Models\User;

class AttachmentPolicy
{
    public function __construct(
        private readonly TicketPolicy $ticketPolicy,
    ) {}

    public function view(User $user, Attachment $attachment): bool
    {
        return $this->ticketPolicy->view($user, $attachment->loadMissing('ticket')->ticket);
    }
}
