<?php

namespace Ernestdefoe\Connect\Api\Controller;

use Ernestdefoe\Connect\Model\ApiKey;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/tags — {id, name} for Zapier's tag dropdown, so a Zap can
 * tag a new discussion by name instead of by numeric ID. Returns an empty list
 * (not an error) when flarum/tags isn't installed, which keeps the Zapier field
 * harmless on forums without tags.
 */
class ListTagsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ApiKey|null $key */
        $key = $request->getAttribute('connectApiKey');

        if (! $key || ! $key->user) {
            return new JsonResponse(['errors' => [['status' => '401', 'code' => 'not_authenticated']]], 401);
        }

        if (! class_exists(\Flarum\Tags\Tag::class)) {
            return new JsonResponse([]);
        }

        try {
            $data = \Flarum\Tags\Tag::query()
                ->whereVisibleTo($key->user)
                ->orderBy('position')
                ->get()
                ->map(fn ($tag) => [
                    'id'   => (int) $tag->id,
                    'name' => $tag->name,
                ])
                ->values()
                ->all();
        } catch (\Throwable $e) {
            return new JsonResponse([]);
        }

        return new JsonResponse($data);
    }
}
