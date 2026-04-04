<?php

namespace App\Enum;

enum StatutReclamation: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case TRAITEE = 'TRAITEE';
    case REJETEE = 'REJETEE';
}