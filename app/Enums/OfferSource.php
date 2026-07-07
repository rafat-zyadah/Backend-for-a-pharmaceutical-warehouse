<?php

namespace App\Enums;

enum OfferSource: string
{
    case BaseProductOffer = 'base_product_offer';
    case PromotionalBasket = 'promotional_basket';
    case NoOffer = 'no_offer';

    public function label(): string
    {
        return match ($this) {
            self::BaseProductOffer => 'عرض صنف أساسي',
            self::PromotionalBasket => 'سلة ترويجية',
            self::NoOffer => 'بدون عرض',
        };
    }
}
