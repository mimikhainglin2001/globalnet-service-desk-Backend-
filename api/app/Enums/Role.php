<?php

namespace App\Enums;

enum Role: string
{
    case CUSTOMER = 'customer';
    case AGENT = 'agent';
    case ADMIN = 'admin';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function isStaff(): bool
    {
        return $this !== self::CUSTOMER;
    }

    /**
     * @return list<self>
     */
    public static function staff(): array
    {
        return [self::AGENT, self::ADMIN];
    }
}
