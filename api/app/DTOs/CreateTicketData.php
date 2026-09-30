<?php

namespace App\DTOs;

use App\Enums\TicketPriority;
use Illuminate\Http\UploadedFile;

final readonly class CreateTicketData
{
    /**
     * @param  list<UploadedFile>  $attachments
     */
    public function __construct(
        public string $subject,
        public string $description,
        public int $categoryId,
        public TicketPriority $priority,
        public ?int $teamId = null,
        public array $attachments = [],
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            subject: $data['subject'],
            description: $data['description'],
            categoryId: (int) $data['category_id'],
            priority: isset($data['priority'])
                ? TicketPriority::from($data['priority'])
                : TicketPriority::NORMAL,
            teamId: isset($data['team_id']) ? (int) $data['team_id'] : null,
            attachments: array_values($data['attachments'] ?? []),
        );
    }

    /**
     * Fingerprint of the request used by the Idempotency-Key check.
     * Files are identified by name, size and content hash.
     */
    public function fingerprint(): string
    {
        return hash('sha256', json_encode([
            'subject' => $this->subject,
            'description' => $this->description,
            'category_id' => $this->categoryId,
            'priority' => $this->priority->value,
            'team_id' => $this->teamId,
            'attachments' => array_map(fn (UploadedFile $file) => [
                $file->getClientOriginalName(),
                $file->getSize(),
                hash_file('sha256', $file->getRealPath()),
            ], $this->attachments),
        ], JSON_THROW_ON_ERROR));
    }
}
