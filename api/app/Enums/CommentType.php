<?php

namespace App\Enums;

enum CommentType: string
{
    case PUBLIC = 'public';
    case INTERNAL = 'internal';

    public function label(): string
    {
        return match ($this) {
            self::PUBLIC => 'Public reply',
            self::INTERNAL => 'Internal note',
        };
    }
}
