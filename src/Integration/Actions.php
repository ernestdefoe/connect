<?php

namespace Ernestdefoe\Connect\Integration;

use Flarum\Api\Client as ApiClient;
use Flarum\Http\UrlGenerator;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * The "create" actions Zapier and Make run as the key's user. Both go through
 * Flarum's own internal API, so the full pipeline applies: permissions,
 * validation, flood control and events (which can fire other automations).
 */
class Actions
{
    public function __construct(
        protected ApiClient $api,
        protected UrlGenerator $url
    ) {
    }

    /** {title, content, tags?: int[]} → {data: {id, title, slug, url}} */
    public function startDiscussion(User $actor, array $body): JsonResponse
    {
        $title = trim((string) Arr::get($body, 'title', ''));
        $content = trim((string) Arr::get($body, 'content', ''));
        $tagIds = array_values(array_filter(array_map('intval', (array) Arr::get($body, 'tags', []))));

        if ($title === '' || $content === '') {
            return Errors::json(422, 'title_and_content_required');
        }

        $data = ['type' => 'discussions', 'attributes' => compact('title', 'content')];
        if ($tagIds) {
            $data['relationships']['tags']['data'] = array_map(fn (int $id) => ['type' => 'tags', 'id' => (string) $id], $tagIds);
        }

        $response = $this->api->withActor($actor)->withBody(['data' => $data])->post('/discussions');
        $created = $this->decode($response);
        if ($response->getStatusCode() >= 400) {
            return new JsonResponse($created, $response->getStatusCode()); // Flarum's own errors, verbatim
        }

        // Flarum 2's serialized slug already carries the id prefix (e.g. "97-title").
        $slug = (string) Arr::get($created, 'data.attributes.slug');

        return new JsonResponse(['data' => [
            'id' => (int) Arr::get($created, 'data.id'),
            'title' => (string) Arr::get($created, 'data.attributes.title', $title),
            'slug' => $slug,
            'url' => $this->base().'/d/'.$slug,
        ]], 201);
    }

    /** {discussionId, content} → {data: {id, discussionId, url}} */
    public function reply(User $actor, array $body): JsonResponse
    {
        $discussionId = (int) Arr::get($body, 'discussionId', 0);
        $content = trim((string) Arr::get($body, 'content', ''));

        if (! $discussionId || $content === '') {
            return Errors::json(422, 'discussion_and_content_required');
        }

        $response = $this->api->withActor($actor)->withBody(['data' => [
            'type' => 'posts',
            'attributes' => ['content' => $content],
            'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => (string) $discussionId]]],
        ]])->post('/posts');
        $created = $this->decode($response);
        if ($response->getStatusCode() >= 400) {
            return new JsonResponse($created, $response->getStatusCode());
        }

        return new JsonResponse(['data' => [
            'id' => (int) Arr::get($created, 'data.id'),
            'discussionId' => $discussionId,
            'url' => $this->base().'/d/'.$discussionId.'/'.(int) Arr::get($created, 'data.attributes.number'),
        ]], 201);
    }

    private function decode(ResponseInterface $response): array
    {
        return (array) json_decode((string) $response->getBody(), true);
    }

    private function base(): string
    {
        return rtrim($this->url->to('forum')->base(), '/');
    }
}
