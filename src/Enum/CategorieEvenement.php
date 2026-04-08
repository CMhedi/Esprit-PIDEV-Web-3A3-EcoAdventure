<?php

namespace App\Enum;

enum CategorieEvenement: string
{
    case TOURNOI = 'TOURNOI';
    case MARATHON = 'MARATHON';
    case COMPETITION = 'COMPETITION';
    case STAGE = 'STAGE';
    case NATURE = 'NATURE';
    case NAUTIQUE = 'NAUTIQUE';
    case AVENTURE = 'AVENTURE';
}