<?php

namespace App\Enums;

enum StatusType: int
{
    case Open = 1;
    case Filled = 2;
    case Cancelled = 3;
}
