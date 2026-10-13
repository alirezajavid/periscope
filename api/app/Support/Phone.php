<?php

namespace App\Support;

class Phone
{
    /**
     * Normalise user input to E.164. Iranian numbers may be written 09xx…, 9xx… or +98…;
     * Persian/Arabic digits are accepted. Anything that doesn't look like a number is
     * returned cleaned-up so validation can reject it.
     */
    public static function normalize(?string $input): ?string
    {
        if ($input === null) {
            return null;
        }

        $digits = strtr($input, [
            '۰' => '0', '۱' => '1', '۲' => '2', '۳' => '3', '۴' => '4',
            '۵' => '5', '۶' => '6', '۷' => '7', '۸' => '8', '۹' => '9',
            '٠' => '0', '١' => '1', '٢' => '2', '٣' => '3', '٤' => '4',
            '٥' => '5', '٦' => '6', '٧' => '7', '٨' => '8', '٩' => '9',
        ]);
        $phone = preg_replace('/[\s\-()]/', '', $digits);

        return match (true) {
            str_starts_with($phone, '+') => $phone,
            str_starts_with($phone, '00') => '+'.substr($phone, 2),
            (bool) preg_match('/^09\d{9}$/', $phone) => '+98'.substr($phone, 1),
            (bool) preg_match('/^9\d{9}$/', $phone) => '+98'.$phone,
            default => $phone,
        };
    }
}
