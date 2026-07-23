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
 * POST /api/connect/actions/posts — a "Post Reply" action. Adds a reply to an
 * existing discussion as the key's user, through Flarum's internal API (full
 * permissions + validation), which re-fires post.created for other automations.
 */
class CreatePostController implements RequestHandlerInterface
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

        $body         = (array) $request->getParsedBody();
        $discussionId = (int) Arr::get($body, 'discussionId', 0);
        $content      = trim((string) Arr::get($body, 'content', ''));

        if (! $discussionId || $content === '') {
            return new JsonResponse(['errors' => [['status' => '422', 'code' => 'discussion_and_content_required']]], 422);
        }

        $payload = ['data' => [
            'type'          => 'posts',
            'attributes'    => ['content' => $content],
            'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => (string) $discussionId]]],
        ]];

        $response = $this->api->withActor($key->user)->withBody($payload)->post('/posts');

        if ($response->getStatusCode() >= 400) {
            return new JsonResponse(json_decode((string) $response->getBody(), true), $response->getStatusCode());
        }

        $created = json_decode((string) $response->getBody(), true);
        $id      = (int) Arr::get($created, 'data.id');
        $number  = (int) Arr::get($created, 'data.attributes.number');

        return new JsonResponse(['data' => [
            'id'           => $id,
            'discussionId' => $discussionId,
            'url'          => rtrim($this->url->to('forum')->base(), '/') . '/d/' . $discussionId . '/' . $number,
        ]], 201);
    }
}
