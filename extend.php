<?php

/*
 * This file is part of mattoid/flarum-ext-money-history.
 *
 * Copyright (c) 2023 mattoid.
 *
 * For the full copyright and license information, please view the LICENSE.md
 * file that was distributed with this source code.
 */

use Flarum\Extend;
use Mattoid\MoneyHistoryAuto\Listeners\CheckinSavedHistory;
use Mattoid\MoneyHistoryAuto\Middleware\DistributeAllHistoryMiddleware;
use Mattoid\MoneyHistoryAuto\Middleware\MoneyRewardsMiddleware;
use Mattoid\MoneyHistoryAuto\Middleware\TransferHistoryMiddleware;

$extend =  [
    (new Extend\Frontend('forum'))
        ->js(__DIR__.'/js/dist/forum.js')
        ->css(__DIR__.'/less/forum.less'),
    (new Extend\Frontend('admin'))
        ->js(__DIR__.'/js/dist/admin.js')
        ->css(__DIR__.'/less/admin.less'),
    new Extend\Locales(__DIR__.'/locale'),

    (new Extend\Middleware("api"))
        ->add(DistributeAllHistoryMiddleware::class)
        ->add(MoneyRewardsMiddleware::class)
        ->add(TransferHistoryMiddleware::class),
];

if (class_exists('Ziven\checkin\Event\checkinUpdated')) {
    $extend[] =
        (new Extend\Event())
            ->listen(\Ziven\checkin\Event\checkinUpdated::class, CheckinSavedHistory::class);
}

return $extend;
