<?php

namespace App\Helpers;

if (! function_exists('App\Helpers\format_currency')) {
    function format_currency(float $number): string
    {
        return number_format($number, 8, '.', '');
    }
}
