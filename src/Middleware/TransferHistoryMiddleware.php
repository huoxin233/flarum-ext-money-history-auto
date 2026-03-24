<?php

namespace Mattoid\MoneyHistoryAuto\Middleware;

use Flarum\Http\RequestUtil;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Mattoid\MoneyHistory\Event\MoneyHistoryEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class TransferHistoryMiddleware implements MiddlewareInterface
{
    private $source = "TRANSFERMONEY";
    private $sourceKey = "mattoid-money-history-auto.forum.searching-recipient";

    public function __construct(private Dispatcher $events)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $response = $handler->handle($request);

        if ($response->getStatusCode() === 201 && strpos($request->getUri(), "/transferMoney")) {
            $transferAmount = Arr::get($request->getParsedBody(), 'data.attributes.moneyTransfer');
            $selectedUsers = json_decode(Arr::get($request->getParsedBody(), 'data.attributes.selectedUsers'), true);

            $batchDelta = -$transferAmount * count($selectedUsers);
            $actor->money += $batchDelta;

            $this->events->dispatch(new MoneyHistoryEvent(
                $actor,
                $batchDelta,
                $this->source,
                $this->sourceKey,
                []
            ));

            $directTransferAmount = Arr::get($request->getParsedBody(), 'data.attributes.money');
            $balanceBefore = $actor->money;
            $balanceAfter = $balanceBefore - $directTransferAmount;

            $this->events->dispatch(new MoneyHistoryEvent(
                $actor,
                -$directTransferAmount,
                $this->source,
                $this->sourceKey,
                [],
                $actor,
                $balanceBefore,
                $balanceAfter
            ));
        }

        return $response;
    }
}
