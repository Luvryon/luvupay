<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PendingApproval = 'pending_approval';   // created, waiting for the end-user to approve at PayPal
    case Active = 'active';
    case PausedByNudge = 'paused_by_nudge';      // Adaptive Nudge: auto-charge paused, awaiting user confirmation
    case Suspended = 'suspended';
    case Resold = 'resold';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
