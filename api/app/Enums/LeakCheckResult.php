<?php

namespace App\Enums;

enum LeakCheckResult: string
{
    case Found = 'found';
    case Ambiguous = 'ambiguous';
    case NotFound = 'not_found';
}
