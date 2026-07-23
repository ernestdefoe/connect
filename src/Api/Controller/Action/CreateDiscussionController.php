<?php

namespace Ernestdefoe\Connect\Api\Controller\Action;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\Api\Client as ApiClient;
use Flarum\Http\UrlGenerator;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/connect/actions/discussions — a Zapier/Make "Create Discussion"
 * action. Runs through Flarum's own internal API client as the key's user, so
 * the full create pipeline applies: permissions, validation, the first post,
 * and events (which in turn can fire other Connect webhooks).
 */
class CreateDiscussionController implements RequestHandlerInterface
{
    public function __construct(
        protected ApiClient $api,
        protected UrlGenerator $url
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ApiKey|null $key */
        $key = $request->getAttribute('connectApiKey');
        if (! $key || ! $key->user) {
            return new JsonResponse(['errors' => [['status' => '401', 'code' => 'not_authenticated']]], 401);
        }
        if (! $key->hasScope('write')) {
            return new JsonResponse(['errors' => [['status' => '403', 'code' => 'insufficient_scope']]], 403);
        }

        $body    = (array) $request->getParsedBody();
        $title   = trim((string) Arr::get($body, 'title', ''));
        $content = trim((string) Arr::get($body, 'content', ''));
        $tagIds  = array_values(array_filter(array_map('intval', (array) Arr::get($body, 'tags', []))));

        if ($title === '' || $content === '') {
            return new JsonResponse(['errors' => [['status' => '422', 'code' => 'title_and_content_required']]], 422);
        }

        $payload = ['data' => ['type' => 'discussions', 'attributes' => compact('title', 'content')]];
        if ($tagIds) {
            $payload['data']['relationships']['tags']['data'] = array_map(
                fn (int $id) => ['type' => 'tags', 'id' => (string) $id],
                $tagIds
            );
        }

        $response = $this->api->withActor($key->user)->withBody($payload)->post('/discussions');

        // Surface Flarum's own errors (permissions, validation) verbatim.
        if ($response->getStatusCode() >= 400) {
            return new JsonResponse(json_decode((string) $response->getBody(), true), $response->getStatusCode());
        }

        $created = json_decode((string) $response->getBody(), true);
        $id      = (int) Arr::get($created, 'data.id');
        // Flarum 2's serialized slug already carries the id prefix (e.g. "97-title").
        $slug    = (string) Arr::get($created, 'data.attributes.slug');

        return new JsonResponse(['data' => [
            'id'    => $id,
            'title' => (string) Arr::get($created, 'data.attributes.title', $title),
            'slug'  => $slug,
            'url'   => rtrim($this->url->to('forum')->base(), '/') . '/d/' . $slug,
        ]], 201);
    }
}
