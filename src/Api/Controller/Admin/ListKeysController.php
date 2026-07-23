<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/keys — admin list of API keys, with their live subscription
 * counts. Admin-gated; the raw token/secret are shown because the admin needs
 * them to configure external services (Zapier/Make/etc.).
 */
class ListKeysController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $keys = ApiKey::query()->withCount('hooks')->with('user')->latest()->get()
            ->map(fn (ApiKey $k) => [
                'id'         => (int) $k->id,
                'label'      => $k->label,
                'token'      => $k->token,
                'secret'     => $k->secret,
                'scopes'     => $k->scopes ?: ['*'],
                'user'       => $k->user?->username,
                'userId'     => (int) $k->user_id,
                'hooks'      => (int) $k->hooks_count,
                'lastUsedAt' => optional($k->last_used_at)->toIso8601String(),
                'createdAt'  => optional($k->created_at)->toIso8601String(),
            ])->values()->all();

        return new JsonResponse(['data' => $keys]);
    }
}
