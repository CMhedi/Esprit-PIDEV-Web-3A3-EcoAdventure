<?php

namespace App\Enum;

enum StatutReservationActivite: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case CONFIRMEE = 'CONFIRMEE';
    case ANNULEE = 'ANNULEE';
}