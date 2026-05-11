<?php

namespace App\Enum;

enum StatutReclamation: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case EN_COURS = 'EN_COURS';
    case TRAITEE = 'TRAITEE';
    case REJETEE = 'REJETEE';
}