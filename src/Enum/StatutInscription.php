<?php

namespace App\Enum;

enum StatutInscription: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case VALIDEE = 'VALIDEE';
    case ANNULEE = 'ANNULEE';
    case CONFIRMEE = 'CONFIRMEE';
}