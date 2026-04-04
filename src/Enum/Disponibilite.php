<?php

namespace App\Enum;

enum Disponibilite: string
{
    case MATIN = 'MATIN';
    case SOIR = 'SOIR';
    case JOURNEE_COMPLETE = 'JOURNEE_COMPLETE';
}