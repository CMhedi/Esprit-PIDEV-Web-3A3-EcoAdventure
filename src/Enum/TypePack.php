<?php

namespace App\Enum;

enum TypePack: string
{
    // Legacy database value kept for compatibility with existing rows.
    case EXPLORER = 'explorer';
    case INDIVIDUEL = 'INDIVIDUEL';
    case GROUPE = 'GROUPE';
    case ENTREPRISE = 'ENTREPRISE';
    case FAMILLE = 'Famille';
}
