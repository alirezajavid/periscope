<?php

namespace App\Enums;

enum LoginMethod: string
{
    case Email = 'email';
    case Phone = 'phone';
    case Google = 'google';
}
