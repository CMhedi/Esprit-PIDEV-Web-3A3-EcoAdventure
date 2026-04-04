<?php

namespace App\Enum;

enum RoleUser: string
{
    case ADMIN = 'ADMIN';
    case COACH = 'COACH';
    case USER_SIMPLE = 'USER_SIMPLE';
}