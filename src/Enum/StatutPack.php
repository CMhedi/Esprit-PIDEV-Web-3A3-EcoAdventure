<?php

namespace App\Enum;

enum StatutPack: string
{
    // Legacy database value kept for compatibility with existing rows.
    case DISPO = 'dispo';
    case ACTIF = 'ACTIF';
    case INACTIF = 'INACTIF';
}
