<?php

namespace App\Enums;

enum DomainEventType: string
{
    case TICKET_CREATED = 'ticket.created';
    case TICKET_ASSIGNED = 'ticket.assigned';
    case TICKET_STATUS_CHANGED = 'ticket.status_changed';
    case TICKET_SLA_BREACHED = 'ticket.sla_breached';
}
