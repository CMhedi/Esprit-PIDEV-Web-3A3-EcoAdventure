<?php

namespace App\Enum;

enum StatutPlanning: string
{
    case BROUILLON = 'BROUILLON';
    case ACTIF = 'ACTIF';
    case ARCHIVE = 'ARCHIVE';
}