<?php

namespace App\Enum;

enum StatutPresence: string
{
    case PRESENT = 'PRESENT';
    case ABSENT = 'ABSENT';
    case NON_MARQUE = 'NON_MARQUE';
}