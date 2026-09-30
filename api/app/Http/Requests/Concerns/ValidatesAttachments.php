<?php

namespace App\Http\Requests\Concerns;

use Illuminate\Validation\Rules\File;

trait ValidatesAttachments
{
    protected function attachmentRules(bool $required = false): array
    {
        $config = config('servicedesk.attachments');

        return [
            'attachments' => [$required ? 'required' : 'sometimes', 'array', 'max:'.$config['max_files']],
            'attachments.*' => [
                'required',
                File::types($config['allowed_mimes'])->max($config['max_size_kb']),
                'extensions:'.implode(',', $config['allowed_mimes']),
            ],
        ];
    }
}
