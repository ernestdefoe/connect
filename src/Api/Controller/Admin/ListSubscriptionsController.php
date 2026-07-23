<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Model\Hook;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/subscriptions — admin view of every live webhook
 * subscription (which service is listening for which event), so an admin can
 * see what's connected and revoke a whole key if something looks off.
 */
class ListSubscriptionsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $hooks = Hook::query()->with('apiKey')->latest()->get()
            ->map(fn (Hook $h) => [
                'id'        => (int) $h->id,
                'event'     => $h->event,
                'targetUrl' => $h->target_url,
                'zapId'     => $h->zap_id,
                'keyId'     => (int) $h->api_key_id,
                'keyLabel'  => $h->apiKey?->label,
                'createdAt' => optional($h->created_at)->toIso8601String(),
            ])->values()->all();

        return new JsonResponse(['data' => $hooks]);
    }
}
