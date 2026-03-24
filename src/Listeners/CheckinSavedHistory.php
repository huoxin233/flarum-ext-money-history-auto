<?php

namespace Mattoid\MoneyHistoryAuto\Listeners;

use Flarum\Settings\SettingsRepositoryInterface;
use Illuminate\Contracts\Events\Dispatcher;
use Mattoid\MoneyHistory\Event\MoneyHistoryEvent;
use Ziven\checkin\Event\checkinUpdated;

class CheckinSavedHistory
{
    private $source = "CHECKINSAVED";
    private $sourceKey = "mattoid-money-history-auto.forum.checkin-saved";

    public function __construct(
        private Dispatcher $events,
        private SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(checkinUpdated $checkin): void
    {
        $checkinRewardMoney = (float) $this->settings->get('ziven-forum-checkin.checkinRewardMoney', 0);

        $this->events->dispatch(new MoneyHistoryEvent(
            $checkin->user,
            $checkinRewardMoney,
            $this->source,
            $this->sourceKey,
            [],
            $checkin->user
        ));
    }
}
