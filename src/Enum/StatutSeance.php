<?php

namespace App\Enum;

enum StatutSeance: string
{
    case PLANIFIEE = 'PLANIFIEE';
    case ANNULEE = 'ANNULEE';
    case TERMINEE = 'TERMINEE';
}