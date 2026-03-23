<?php

namespace Mattoid\MoneyHistoryAuto\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\Locale\Translator;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Mattoid\MoneyHistory\Event\MoneyHistoryEvent;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

class UserSaveMiddleware implements MiddlewareInterface
{
    private $events;
    private $source = "USERWILLBESAVED";
    private $sourceKey;
    private $sourceDesc = '系统/管理员发放';

    public function __construct(Dispatcher $events, Translator $translator)
    {
        $this->events = $events;
        $this->sourceKey = "mattoid-money-history-auto.forum.system-rewards";
        $this->sourceDesc = $translator->trans("mattoid-money-history-auto.forum.system-rewards");
    }

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $attributes = Arr::get($request->getParsedBody(), 'data.attributes');

        // Fetch the current balance before handling the request.
        $balanceBefore = 0;
        $userId = Arr::get($request->getParsedBody(), 'data.id');
        if ($request->getMethod() == 'PATCH' && strpos($request->getUri(), '/users/') && isset($attributes['money'])) {
            // Fetch the current balance before the update is applied.
            $preSaveUser = User::query()->where("id", $userId)->first();
            $balanceBefore = $preSaveUser ? $preSaveUser->money : 0;

            // Allow the user object to be updated by the next handler
            $response = $handler->handle($request);

            if ($response->getStatusCode() === 200) {
                // Refetch the user to capture the balance after the save operation.
                $user = User::query()->where('id', $userId)->first();
                $balanceAfter = $user ? $user->money : $balanceBefore;

                if ($user && $balanceAfter != $balanceBefore) {
                    $balanceDelta = $balanceAfter - $balanceBefore;
                    $this->events->dispatch(new MoneyHistoryEvent(
                        $user,
                        $balanceDelta,
                        $this->source,
                        $this->sourceDesc,
                        $this->sourceKey,
                        $actor,
                        $balanceBefore,
                        $balanceAfter
                    ));
                }
            }

            return $response;
        }

        // For all other requests, just pass through
        return $handler->handle($request);
    }
}
