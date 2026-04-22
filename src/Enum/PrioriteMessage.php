<?php

namespace App\Enum;

enum PrioriteMessage: string
{
    case FAIBLE = 'FAIBLE';
    case NORMAL = 'NORMAL';
    case URGENT = 'URGENT';

    public function label(): string
    {
        return match ($this) {
            self::FAIBLE => 'Faible',
            self::NORMAL => 'Normal',
            self::URGENT => 'Urgent',
        };
    }

    public function soundLevel(): string
    {
        return match ($this) {
            self::FAIBLE => 'silent',
            self::NORMAL => 'normal',
            self::URGENT => 'urgent',
        };
    }
}
