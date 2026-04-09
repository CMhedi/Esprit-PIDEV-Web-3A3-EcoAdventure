<?php

namespace App\Enum;

enum Statut: string
{
    case DISPONIBLE = 'DISPONIBLE';
    case INDISPONIBLE = 'INDISPONIBLE';

    public static function getValues(): array
    {
        return array_column(self::cases(), 'value');
    }
}