<?php

namespace Database\Seeders;

use App\Enums\CommentType;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Ticket;
use App\Models\User;
use App\Services\SlaService;
use Illuminate\Database\Seeder;

/**
 * Sample tickets spread over statuses, priorities and ages so the queue,
 * filters and dashboard (overdue, first response, resolved in 7 days) have
 * meaningful data. Skipped when tickets already exist.
 */
class TicketSeeder extends Seeder
{
    /**
     * [subject, category, priority, status, requester, assignee, age in hours, first response after hours]
     */
    private const TICKETS = [
        ['Unable to access customer portal', 'Technical Issue', TicketPriority::HIGH, TicketStatus::OPEN, 'customer@globalnet.test', null, 2, null],
        ['Question about latest invoice', 'Billing', TicketPriority::NORMAL, TicketStatus::IN_PROGRESS, 'customer@globalnet.test', 'agent2@globalnet.test', 20, 1],
        ['Fibre connection drops every evening', 'Network Outage', TicketPriority::URGENT, TicketStatus::IN_PROGRESS, 'customer2@globalnet.test', 'agent@globalnet.test', 6, 0.5],
        ['Need to reset my account password', 'Account', TicketPriority::LOW, TicketStatus::WAITING_ON_CUSTOMER, 'customer3@globalnet.test', 'agent@globalnet.test', 30, 3],
        ['Upgrade to 1 Gbps plan', 'Plan Change', TicketPriority::NORMAL, TicketStatus::RESOLVED, 'customer2@globalnet.test', 'agent2@globalnet.test', 72, 2],
        ['Router firmware update failed', 'Technical Issue', TicketPriority::HIGH, TicketStatus::OPEN, 'customer3@globalnet.test', null, 12, null],
        ['Duplicate charge on credit card', 'Billing', TicketPriority::URGENT, TicketStatus::OPEN, 'customer@globalnet.test', null, 9, null],
        ['How do I change my contact email?', 'General Enquiry', TicketPriority::LOW, TicketStatus::CLOSED, 'customer2@globalnet.test', 'agent@globalnet.test', 120, 4],
        ['Slow speeds during business hours', 'Network Outage', TicketPriority::NORMAL, TicketStatus::RESOLVED, 'customer3@globalnet.test', 'agent@globalnet.test', 50, 1.5],
        ['Cancel add-on TV package', 'Plan Change', TicketPriority::LOW, TicketStatus::OPEN, 'customer@globalnet.test', null, 1, null],
    ];

    public function run(SlaService $sla): void
    {
        if (Ticket::query()->exists()) {
            return;
        }

        $users = User::query()->pluck('id', 'email');
        $categories = Category::query()->get()->keyBy('name');

        foreach (self::TICKETS as [$subject, $categoryName, $priority, $status, $requester, $assignee, $ageHours, $responseHours]) {
            $category = $categories[$categoryName];
            $createdAt = now()->subMinutes((int) ($ageHours * 60));

            $ticket = new Ticket([
                'subject' => $subject,
                'description' => "{$subject}. Please help as soon as possible.",
                'category_id' => $category->id,
                'priority' => $priority,
                'status' => $status,
                'requester_id' => $users[$requester],
                'assignee_id' => $assignee ? $users[$assignee] : null,
                'team_id' => $category->team_id,
                'due_at' => $sla->calculateDueAt($priority, $createdAt),
                'first_responded_at' => $responseHours !== null ? $createdAt->copy()->addMinutes((int) ($responseHours * 60)) : null,
                'resolved_at' => in_array($status, TicketStatus::finished(), true) ? $createdAt->copy()->addHours(24) : null,
                'closed_at' => $status === TicketStatus::CLOSED ? $createdAt->copy()->addHours(48) : null,
            ]);
            $ticket->created_at = $createdAt;
            $ticket->updated_at = $createdAt;
            $ticket->save();

            $this->seedThread($ticket, $users[$requester], $assignee ? $users[$assignee] : null);
        }
    }

    private function seedThread(Ticket $ticket, int $requesterId, ?int $assigneeId): void
    {
        Comment::create([
            'ticket_id' => $ticket->id,
            'user_id' => $requesterId,
            'type' => CommentType::PUBLIC,
            'body' => 'Adding more details: this started happening this week.',
        ]);

        if ($assigneeId !== null) {
            Comment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $assigneeId,
                'type' => CommentType::PUBLIC,
                'body' => 'Thanks for reaching out, I am looking into this now.',
            ]);

            Comment::create([
                'ticket_id' => $ticket->id,
                'user_id' => $assigneeId,
                'type' => CommentType::INTERNAL,
                'body' => 'Internal: checked account history, no previous incidents.',
            ]);
        }
    }
}
