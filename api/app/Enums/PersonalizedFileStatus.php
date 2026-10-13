<?php

namespace App\Enums;

enum PersonalizedFileStatus: string
{
    case Building = 'building';
    case Ready = 'ready';
    case Failed = 'failed';
}
