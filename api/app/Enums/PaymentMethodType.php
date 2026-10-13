<?php

namespace App\Enums;

enum PaymentMethodType: string
{
    case Card = 'card';
    case Paypal = 'paypal';
    case Crypto = 'crypto';
}
