<?php

namespace App\Enum;

enum CategorieAct: string
{
    case FITNESS = 'FITNESS';
    case RUNNING = 'RUNNING';
    case FOOTBALL = 'FOOTBALL';
    case BASKETBALL = 'BASKETBALL';
    case TENNIS = 'TENNIS';
    case NATATION = 'NATATION';
    case RANDONNEE = 'RANDONNEE';
    case CYCLISME = 'CYCLISME';
    case YOGA = 'YOGA';
    case AUTRE = 'AUTRE';
}