<?php

namespace Mattoid\MoneyHistoryAuto\Middleware;

use Flarum\Http\RequestUtil;
use Flarum\Locale\Translator;
use Flarum\Post\Post;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Contracts\Events\Dispatcher;
use Illuminate\Support\Arr;
use Mattoid\MoneyHistory\Event\MoneyAllHistoryEvent;
use Mattoid\MoneyHistory\Event\MoneyHistoryEvent;
use Mattoid\OperateLog\model\UserOperateLog;
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
        
        $oldMoney = 0;
        $userId = Arr::get($request->getParsedBody(), 'data.id');
        if ($request->getMethod() == 'PATCH' && strpos($request->getUri(), '/users/') && isset($attributes['money'])) {
            $preSaveUser = User::query()->where("id", $userId)->first();
            $oldMoney = $preSaveUser ? $preSaveUser->money : 0;
            $response = $handler->handle($request);

            if ($response->getStatusCode() === 200) {
                $user = User::query()->where('id', $userId)->first();
                $newMoney = $user ? $user->money : $oldMoney;

                if ($user && $newMoney != $oldMoney) {
                    $moneyDifference = $newMoney - $oldMoney;
                    $this->events->dispatch(new MoneyHistoryEvent($user, $moneyDifference, $this->source, $this->sourceDesc, $this->sourceKey, $actor, $oldMoney));
                }
            }

            return $response;
        }
        return $handler->handle($request);
    }
}
