<?php

namespace App\Enums;

enum QuestionStatus: string
{
    case New = 'new';
    case Starred = 'starred';
    case Answered = 'answered';
}
