<?php

namespace App\Enums;

enum ListingStatus: string
{
    case Active = 'active';
    case Reserved = 'reserved';
    case Sold = 'sold';
    case Expired = 'expired';
    case Cancelled = 'cancelled';
}
