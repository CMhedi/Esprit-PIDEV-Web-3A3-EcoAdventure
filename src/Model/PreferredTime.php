<?php

namespace App\Model;

enum PreferredTime: string
{
    case MORNING = 'morning';
    case AFTERNOON = 'afternoon';
    case EVENING = 'evening';

    public function label(): string
    {
        return match ($this) {
            self::MORNING => '🌅 Matin (avant 12h)',
            self::AFTERNOON => '☀️ Après-midi (12h-18h)',
            self::EVENING => '🌙 Soir (après 18h)',
        };
    }
}