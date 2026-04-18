<?php

namespace App\Enum;

enum StatutReservationEvenement: string
{
    case EN_ATTENTE = 'EN_ATTENTE';
    case LISTE_ATTENTE = 'LISTE_ATTENTE';
    case CONFIRMEE = 'CONFIRMEE';
    case ANNULEE = 'ANNULEE';
}