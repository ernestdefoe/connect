<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DELETE /api/connect/keys/{id} — revoke a key. Its subscriptions cascade away,
 * so every Zap/scenario using it stops immediately.
 */
class DeleteKeyController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $id = (int) Arr::get($request->getAttribute('routeParameters') ?? [], 'id', 0);

        ApiKey::query()->whereKey($id)->delete();

        return new EmptyResponse(204);
    }
}
