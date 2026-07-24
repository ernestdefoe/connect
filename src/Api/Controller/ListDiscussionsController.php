<?php

namespace Ernestdefoe\Connect\Api\Controller;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\Discussion\Discussion;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/discussions — recent discussions the key's user is allowed to
 * see, shaped as {id, title} for Zapier's dynamic dropdowns so nobody has to
 * type a numeric discussion ID by hand. Paged via ?page (Zapier sends 0-based).
 */
class ListDiscussionsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ApiKey|null $key */
        $key = $request->getAttribute('connectApiKey');

        if (! $key || ! $key->user) {
            return new JsonResponse(['errors' => [['status' => '401', 'code' => 'not_authenticated']]], 401);
        }

        $query = (array) $request->getQueryParams();
        $page  = max(0, (int) Arr::get($query, 'page', 0));
        $limit = min(100, max(1, (int) Arr::get($query, 'limit', 50)));

        $data = Discussion::query()
            ->whereVisibleTo($key->user)
            ->whereNull('hidden_at')
            ->latest('last_posted_at')
            ->skip($page * $limit)
            ->take($limit)
            ->get()
            ->map(fn (Discussion $d) => [
                'id'    => (int) $d->id,
                'title' => $d->title,
            ])
            ->values()
            ->all();

        return new JsonResponse($data);
    }
}
