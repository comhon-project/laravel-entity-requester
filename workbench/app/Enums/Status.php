<?php

namespace App\Enums;

enum Status: int
{
    case Pending = 1;
    case Approved = 2;
    case Rejected = 3;
}
