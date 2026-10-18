<?php

namespace App\Enums;

enum TransactionType: string
{
    case Charge = 'charge';
    case Refund = 'refund';
    case CharityPayout = 'charity_payout';
    case MerchantRetention = 'merchant_retention';
    case ResaleSale = 'resale_sale';
    case ResalePayout = 'resale_payout';
    case ResaleCapture = 'resale_capture';
}
