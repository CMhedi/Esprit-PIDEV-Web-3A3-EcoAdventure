<?php

namespace App\Enum;

enum CategorieEvenement: string
{
    case TOURNOI = 'TOURNOI';
    case MARATHON = 'MARATHON';
    case COMPETITION = 'COMPETITION';
    case STAGE = 'STAGE';
    // Backing values from DB and FrontEnd
    case NATURE = 'nature';
    case NAUTIQUE = 'nautique';
    case AVENTURE = 'aventure';
    case NATURE_CAP = 'Nature';
    case NAUTIQUE_CAP = 'Nautique';
    case AVENTURE_CAP = 'Aventure';
}