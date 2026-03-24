<?php

namespace Mattoid\MoneyHistoryAuto\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Mattoid\MoneyHistory\Event\MoneyAllHistoryEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class DistributeAllHistoryMiddleware implements MiddlewareInterface
{
    private $source = "BATCHDISTRIBUTION";
    private $sourceKey = "mattoid-money-history-auto.forum.system-rewards";

    public function __construct(private Dispatcher $events)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $response = $handler->handle($request);

        $dryRun = Arr::get($request->getParsedBody(), 'dryRun');

        if ($response->getStatusCode() === 200 && ! $dryRun && strpos($request->getUri(), '/money-to-all')) {
            $amount = Arr::get($request->getParsedBody(), 'amount');

            User::query()->chunk(500, function ($userList) use ($amount, $actor): void {
                $this->events->dispatch(new MoneyAllHistoryEvent(
                    $userList->all(),
                    (float) $amount,
                    $this->source,
                    $this->sourceKey,
                    [],
                    $actor
                ));
            });
        }

        return $response;
    }
}
