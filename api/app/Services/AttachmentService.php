<?php

namespace App\Services;

use App\Models\Attachment;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

class AttachmentService
{
    /**
     * Paths written during the current request, so a rolled-back transaction
     * can remove files that no longer have a database row.
     *
     * @var list<string>
     */
    private array $writtenPaths = [];

    /**
     * Files are stored on a private disk under a random name; the original
     * name is only kept in the database and never used as a path.
     *
     * @param  list<UploadedFile>  $files
     * @return list<Attachment>
     */
    public function store(Ticket $ticket, User $uploader, array $files): array
    {
        if ($files === []) {
            return [];
        }

        $max = config('servicedesk.attachments.max_files');
        $existing = $ticket->attachments()->count();

        if ($existing + count($files) > $max) {
            throw ValidationException::withMessages([
                'attachments' => ["A ticket may have at most {$max} attachments ({$existing} already uploaded)."],
            ]);
        }

        return array_map(function (UploadedFile $file) use ($ticket, $uploader) {
            $path = $file->storeAs(
                config('servicedesk.attachments.directory')."/{$ticket->id}",
                Str::uuid().'.'.$file->extension(),
                ['disk' => $this->disk()],
            );

            $this->writtenPaths[] = $path;

            return $ticket->attachments()->create([
                'user_id' => $uploader->id,
                'original_name' => Str::limit($file->getClientOriginalName(), 250, ''),
                'file_path' => $path,
                'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
                'file_size' => $file->getSize(),
            ]);
        }, $files);
    }

    public function download(Attachment $attachment): StreamedResponse
    {
        return Storage::disk($this->disk())->download(
            $attachment->file_path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }

    public function discardWrittenFiles(): void
    {
        if ($this->writtenPaths !== []) {
            Storage::disk($this->disk())->delete($this->writtenPaths);
            $this->writtenPaths = [];
        }
    }

    private function disk(): string
    {
        return config('servicedesk.attachments.disk');
    }
}
