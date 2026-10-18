<?php

namespace App\Enums;

enum EntitlementStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Acknowledged = 'acknowledged';
    case Failed = 'failed';
    case Retrying = 'retrying';
}
