<?php

namespace Ernestdefoe\Connect\Integration;

use Ernestdefoe\Connect\Support\Tags;
use Ernestdefoe\Connect\Webhook\Audience;
use Ernestdefoe\Connect\Webhook\Payload;
use Flarum\Discussion\Discussion;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Post;
use Flarum\User\User;

/**
 * Read-only data for Zapier: the dropdown pickers (discussions, tags) and the
 * sample items shown while a Zap is set up. Everything is limited to what the
 * key's user could see in the forum.
 */
class Lookups
{
    private const SAMPLES = 3;

    public function __construct(
        protected UrlGenerator $url
    ) {
    }

    /** {id, title} of recent visible discussions, paged (Zapier sends a 0-based page). */
    public function discussions(User $viewer, int $page, int $limit): array
    {
        $limit = min(100, max(1, $limit));

        return Discussion::query()
            ->whereVisibleTo($viewer)
            ->whereNull('hidden_at')
            ->latest('last_posted_at')
            ->skip(max(0, $page) * $limit)
            ->take($limit)
            ->get(['id', 'title'])
            ->map(fn (Discussion $d) => ['id' => (int) $d->id, 'title' => $d->title])
            ->values()->all();
    }

    /** {id, name} of the visible tags; empty without flarum/tags, so the Zapier field stays harmless. */
    public function tags(User $viewer): array
    {
        if (! Tags::enabled()) {
            return [];
        }

        return \Flarum\Tags\Tag::query()
            ->whereVisibleTo($viewer)
            ->orderBy('position')
            ->get(['id', 'name'])
            ->map(fn ($tag) => ['id' => (int) $tag->id, 'name' => $tag->name])
            ->values()->all();
    }

    /**
     * Recent real items shaped exactly like the live webhook payload (both come
     * from Payload: Zapier requires the sample and live shapes to match).
     */
    public function samples(User $viewer, string $event): array
    {
        $base = rtrim($this->url->to('forum')->base(), '/');

        return match ($event) {
            'discussion.created' => Discussion::query()->whereVisibleTo($viewer)
                ->where('is_private', false)->whereNull('hidden_at')
                ->with(Tags::enabled() ? ['user', 'tags'] : ['user'])
                ->latest()->limit(self::SAMPLES)->get()
                ->map(fn (Discussion $d) => Payload::discussion($d, $base))->values()->all(),

            'post.created' => Post::query()->whereVisibleTo($viewer)
                ->where('type', 'comment')->whereNull('hidden_at')->where('number', '>', 1)
                ->with('user')->latest()->limit(self::SAMPLES)->get()
                ->map(fn (Post $p) => Payload::post($p, $base))->values()->all(),

            'user.registered' => User::query()->whereVisibleTo($viewer)
                ->latest('joined_at')->limit(self::SAMPLES)->get()
                ->map(fn (User $u) => array_replace(Payload::user($u, $base), ['email' => Audience::canSeeEmail($viewer, $u) ? $u->email : null]))
                ->values()->all(),

            default => [],
        };
    }
}
