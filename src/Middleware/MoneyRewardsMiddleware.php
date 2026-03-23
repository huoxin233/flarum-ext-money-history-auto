<?php

namespace Mattoid\MoneyHistoryAuto\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\Locale\Translator;
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
    private $events;
    private $source = "MONEYREWARDS";
    private $sourceKey;
    private $sourceDesc;

    public function __construct(Dispatcher $events, Translator $translator)
    {
        $this->events = $events;
        $this->sourceKey = "mattoid-money-history-auto.forum.admin-rewards";
        $this->sourceDesc = $translator->trans("mattoid-money-history-auto.forum.admin-rewards");
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);

        // Capture state before handling for accurate history logging
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

            // deduction from actor
            if (! $createMoney) {
                $this->events->dispatch(new MoneyHistoryEvent(
                    $actor,
                    -$amount,
                    $this->source,
                    $this->sourceDesc,
                    $this->sourceKey,
                    $actor,
                    $actorBalanceBefore,
                    $actorBalanceBefore - $amount
                ));
            }

            if ($targetUser) {
                // Refetch the target user to capture the updated balance after the reward.
                $user = User::query()->where('id', $targetUser->id)->first();
                $targetBalanceAfter = $user ? $user->money : $targetBalanceBefore;
                $this->events->dispatch(new MoneyHistoryEvent(
                    $user,
                    $amount,
                    $this->source,
                    $this->sourceDesc,
                    $this->sourceKey,
                    $actor,
                    $targetBalanceBefore,
                    $targetBalanceAfter
                ));
            }
        }

        return $response;
    }
}
