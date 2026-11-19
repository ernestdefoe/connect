<?php

namespace Ernestdefoe\Connect\Api\Controller;

use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Support\Tags;
use Ernestdefoe\Connect\Webhook\Audience;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Ernestdefoe\Connect\Webhook\Payload;
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
 * webhook payload for that event (both come from Payload). Zapier calls this
 * during Zap setup (performList) so users can map fields against real data
 * without waiting for a live trigger. It shows only what the key's user could
 * see, exactly as the live webhooks do.
 */
class SampleController implements RequestHandlerInterface
{
    private const LIMIT = 3;

    public function __construct(
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
        if (! $key->hasScope('read')) {
            return new JsonResponse(['errors' => [['status' => '403', 'code' => 'insufficient_scope']]], 403);
        }

        $event = (string) Arr::get($request->getAttribute('routeParameters') ?? [], 'event', '');
        if (! EventRegistry::exists($event)) {
            return new JsonResponse(['errors' => [['status' => '404', 'code' => 'unknown_event']]], 404);
        }

        $base = rtrim($this->url->to('forum')->base(), '/');

        return new JsonResponse(match ($event) {
            'discussion.created' => $this->discussions($key->user, $base),
            'post.created' => $this->posts($key->user, $base),
            'user.registered' => $this->users($key->user, $base),
            default => [],
        });
    }

    private function discussions(User $viewer, string $base): array
    {
        return Discussion::query()->whereVisibleTo($viewer)
            ->where('is_private', false)->whereNull('hidden_at')
            ->with(Tags::enabled() ? ['user', 'tags'] : ['user'])
            ->latest()->limit(self::LIMIT)->get()
            ->map(fn (Discussion $d) => Payload::discussion($d, $base))->values()->all();
    }

    private function posts(User $viewer, string $base): array
    {
        return Post::query()->whereVisibleTo($viewer)
            ->where('type', 'comment')->whereNull('hidden_at')->where('number', '>', 1)
            ->with('user')->latest()->limit(self::LIMIT)->get()
            ->map(fn (Post $p) => Payload::post($p, $base))->values()->all();
    }

    private function users(User $viewer, string $base): array
    {
        return User::query()->whereVisibleTo($viewer)
            ->latest('joined_at')->limit(self::LIMIT)->get()
            ->map(fn (User $u) => array_replace(Payload::user($u, $base), ['email' => Audience::canSeeEmail($viewer, $u) ? $u->email : null]))
            ->values()->all();
    }
}
