<?php

namespace Ernestdefoe\Connect\Api\Controller;

use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Model\Hook;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * DELETE /api/connect/hooks/{id} — REST Hook unsubscribe. Only removes hooks
 * owned by the presenting key, so one connection can't tear down another's.
 */
class UnsubscribeHookController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ApiKey|null $key */
        $key = $request->getAttribute('connectApiKey');
        if (! $key) {
            return new JsonResponse(['errors' => [['status' => '401', 'code' => 'not_authenticated']]], 401);
        }

        $id = (int) Arr::get($request->getAttribute('routeParameters') ?? [], 'id', 0);

        Hook::query()->whereKey($id)->where('api_key_id', $key->id)->delete();

        return new EmptyResponse(204);
    }
}
