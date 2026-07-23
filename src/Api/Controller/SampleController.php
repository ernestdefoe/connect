<?php

namespace Ernestdefoe\Connect\Api\Controller;

use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Flarum\Discussion\Discussion;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/samples/{event} — recent real items shaped exactly like the
 * webhook payload for that event. Zapier calls this during Zap setup (performList)
 * so users can map fields against real data without waiting for a live trigger.
 * The shape here MUST match DispatchWebhooks for the same event.
 */
class SampleController implements RequestHandlerInterface
{
    public function __construct(
        protected UrlGenerator $url
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        /** @var ApiKey|null $key */
        $key = $request->getAttribute('connectApiKey');
        if (! $key) {
            return new JsonResponse(['errors' => [['status' => '401', 'code' => 'not_authenticated']]], 401);
        }

        $event = (string) Arr::get($request->getAttribute('routeParameters') ?? [], 'event', '');
        if (! EventRegistry::exists($event)) {
            return new JsonResponse(['errors' => [['status' => '404', 'code' => 'unknown_event']]], 404);
        }

        $base = rtrim($this->url->to('forum')->base(), '/');

        $data = match ($event) {
            'discussion.created' => Discussion::query()
                ->where('is_private', false)->whereNull('hidden_at')
                ->latest()->limit(3)->get()
                ->map(fn (Discussion $d) => [
                    'id' => (int) $d->id, 'title' => $d->title, 'slug' => $d->slug,
                    'url' => $base . '/d/' . $d->id . '-' . $d->slug,
                    'author' => $d->user?->display_name, 'authorId' => (int) $d->user_id,
                    'createdAt' => optional($d->created_at)->toIso8601String(),
                ])->values()->all(),

            'post.created' => Post::query()
                ->where('type', 'comment')->whereNull('hidden_at')->where('number', '>', 1)
                ->latest()->limit(3)->get()
                ->map(fn (Post $p) => [
                    'id' => (int) $p->id, 'discussionId' => (int) $p->discussion_id,
                    'url' => $base . '/d/' . $p->discussion_id . '/' . $p->number,
                    'content' => $p->content, 'author' => $p->user?->display_name,
                    'authorId' => (int) $p->user_id, 'createdAt' => optional($p->created_at)->toIso8601String(),
                ])->values()->all(),

            'user.registered' => User::query()
                ->latest('joined_at')->limit(3)->get()
                ->map(fn (User $u) => [
                    'id' => (int) $u->id, 'username' => $u->username, 'name' => $u->display_name,
                    'email' => $u->email, 'url' => $base . '/u/' . $u->username,
                    'createdAt' => optional($u->joined_at)->toIso8601String(),
                ])->values()->all(),

            default => [],
        };

        return new JsonResponse($data);
    }
}
