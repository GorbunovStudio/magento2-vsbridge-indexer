<?php

declare(strict_types=1);

namespace Divante\VsbridgeIndexerCore\Api\Cache;

interface DataTypeInterface
{
    const TYPE_PRODUCT = 'product';
    const TYPE_CATEGORY = 'category';
    const TYPE_URL_REWRITE = 'url_rewrite';
    const TYPE_RUSH_ADDON = 'rush_addon';
    const TYPE_STATISTIC_VALUE = 'statistic_value';
    const TYPE_SETTING = 'setting';
    const TYPE_GIFT_CARD_TEMPLATE = 'gift_card_template';
    const TYPE_STORE_RATING = 'store_rating';
}