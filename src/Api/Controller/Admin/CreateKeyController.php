<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/connect/keys — mint an API key. Defaults to acting as the admin who
 * created it; a different user id can be supplied. Scopes default to read+write.
 */
class CreateKeyController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $attrs  = (array) Arr::get($request->getParsedBody() ?? [], 'data', []);
        $label  = trim((string) Arr::get($attrs, 'label', '')) ?: 'API key';
        $userId = (int) (Arr::get($attrs, 'userId') ?: $actor->id);
        $scopes = array_values(array_filter((array) Arr::get($attrs, 'scopes', ['read', 'write'])));

        if (! User::query()->whereKey($userId)->exists()) {
            return new JsonResponse(['errors' => [['status' => '422', 'code' => 'unknown_user']]], 422);
        }

        $key = ApiKey::build($label, $userId, $scopes);
        $key->save();
        $key->loadCount('hooks');
        $key->load('user');

        return new JsonResponse(['data' => [
            'id'         => (int) $key->id,
            'label'      => $key->label,
            'token'      => $key->token,
            'secret'     => $key->secret,
            'scopes'     => $key->scopes ?: ['*'],
            'user'       => $key->user?->username,
            'userId'     => (int) $key->user_id,
            'hooks'      => 0,
            'lastUsedAt' => null,
            'createdAt'  => optional($key->created_at)->toIso8601String(),
        ]], 201);
    }
}
