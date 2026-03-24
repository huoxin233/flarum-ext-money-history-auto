<?php

namespace Mattoid\MoneyHistoryAuto\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Mattoid\MoneyHistory\Event\MoneyHistoryEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class MoneyRewardsMiddleware implements MiddlewareInterface
{
    private $source = "MONEYREWARDS";
    private $sourceKey = "mattoid-money-history-auto.forum.admin-rewards";

    public function __construct(private Dispatcher $events)
    {
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        $postId = Arr::get($request->getAttribute("routeParameters"), "id");
        $targetUser = null;
        $targetBalanceBefore = 0;

        if ($postId && preg_match('/\/posts\/\d*\/money-rewards/', $request->getUri())) {
            $post = Post::query()->where('id', $postId)->first();

            if ($post) {
                $targetUser = User::query()->where('id', $post->user_id)->first();
                $targetBalanceBefore = $targetUser ? $targetUser->money : 0;
            }
        }

        $actorBalanceBefore = $actor->money;
        $response = $handler->handle($request);

        if ($response->getStatusCode() === 201 && preg_match('/\/posts\/\d*\/money-rewards/', $request->getUri())) {
            $amount = Arr::get($request->getParsedBody(), 'data.attributes.amount');
            $createMoney = Arr::get($request->getParsedBody(), 'data.attributes.createMoney');

            if (! $createMoney) {
                $this->events->dispatch(new MoneyHistoryEvent(
                    $actor,
                    -$amount,
                    $this->source,
                    $this->sourceKey,
                    [],
                    $actor,
                    $actorBalanceBefore,
                    $actorBalanceBefore - $amount
                ));
            }

            if ($targetUser) {
                $user = User::query()->where('id', $targetUser->id)->first();
                $targetBalanceAfter = $user ? $user->money : $targetBalanceBefore;

                $this->events->dispatch(new MoneyHistoryEvent(
                    $user,
                    $amount,
                    $this->source,
                    $this->sourceKey,
                    [],
                    $actor,
                    $targetBalanceBefore,
                    $targetBalanceAfter
                ));
            }
        }

        return $response;
    }
}
