<?php

namespace Ernestdefoe\Connect\Api\Controller;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\Settings\SettingsRepositoryInterface;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/me — the connection test + label used by Zapier's auth step.
 * Returns 401 unless a valid Connect key resolved an actor.
 */
class MeController implements RequestHandlerInterface
{
    public function __construct(
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ApiKey|null $key */
        $key = $request->getAttribute('connectApiKey');

        if (! $key || ! $key->user) {
            return new JsonResponse(['errors' => [['status' => '401', 'code' => 'not_authenticated']]], 401);
        }

        // The key's user is the authoritative actor; the request actor is reset
        // to guest by Flarum's own auth, which doesn't recognise Connect keys.
        return new JsonResponse(['data' => ['attributes' => [
            'forumTitle' => (string) $this->settings->get('forum_title'),
            'user'       => $key->user->username,
            'userId'     => (int) $key->user->id,
            'keyLabel'   => $key->label,
        ]]]);
    }
}
