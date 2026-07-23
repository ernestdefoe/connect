<?php

namespace Ernestdefoe\Connect\Api\Controller;

use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Model\Hook;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/connect/hooks — REST Hook subscribe. The external service sends
 * { event, targetUrl }; we store it under the presented key and return { id }
 * so it can unsubscribe later. Idempotent per (key, event, url).
 */
class SubscribeHookController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ApiKey|null $key */
        $key = $request->getAttribute('connectApiKey');
        if (! $key) {
            return new JsonResponse(['errors' => [['status' => '401', 'code' => 'not_authenticated']]], 401);
        }

        $body   = (array) $request->getParsedBody();
        $event  = (string) Arr::get($body, 'event', '');
        $target = (string) Arr::get($body, 'targetUrl', '');
        $zapId  = Arr::get($body, 'zapId');

        if (! EventRegistry::exists($event)) {
            return new JsonResponse(['errors' => [['status' => '422', 'code' => 'unknown_event', 'detail' => $event]]], 422);
        }
        if (! filter_var($target, FILTER_VALIDATE_URL) || ! str_starts_with($target, 'https://')) {
            return new JsonResponse(['errors' => [['status' => '422', 'code' => 'invalid_target_url']]], 422);
        }

        $hook = Hook::query()->firstOrNew([
            'api_key_id' => $key->id,
            'event'      => $event,
            'target_url' => $target,
        ]);
        $hook->zap_id = $zapId ? (string) $zapId : $hook->zap_id;
        $hook->save();

        return new JsonResponse(['id' => (int) $hook->id, 'event' => $event], 201);
    }
}
