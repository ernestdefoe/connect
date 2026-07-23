<?php

namespace Ernestdefoe\Connect\Listener;

use Ernestdefoe\Connect\Webhook\Dispatcher;
use Flarum\Discussion\Event\Started;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Event\Posted;
use Flarum\User\Event\Registered;
use Illuminate\Contracts\Events\Dispatcher as Events;

/**
 * Bridges Flarum's domain events to Connect trigger events, shaping a small,
 * stable payload for each. Payloads stay flat and predictable so they map
 * cleanly in Zapier/Make and don't leak internal structure.
 */
class DispatchWebhooks
{
    public function __construct(
        protected Dispatcher $webhooks,
        protected UrlGenerator $url
    ) {
    }

    public function subscribe(Events $events): void
    {
        $events->listen(Started::class, [$this, 'discussionStarted']);
        $events->listen(Posted::class, [$this, 'postPosted']);
        $events->listen(Registered::class, [$this, 'userRegistered']);
    }

    public function discussionStarted(Started $event): void
    {
        $d = $event->discussion;

        $this->webhooks->fire('discussion.created', [
            'id'        => (int) $d->id,
            'title'     => $d->title,
            'slug'      => $d->slug,
            'url'       => $this->base() . '/d/' . $d->id . '-' . $d->slug,
            'author'    => $d->user?->display_name,
            'authorId'  => (int) $d->user_id,
            'createdAt' => optional($d->created_at)->toIso8601String(),
        ]);
    }

    public function postPosted(Posted $event): void
    {
        $p = $event->post;

        // The first post of a discussion already fires discussion.created.
        if ($p->number <= 1) {
            return;
        }

        $this->webhooks->fire('post.created', [
            'id'           => (int) $p->id,
            'discussionId' => (int) $p->discussion_id,
            'url'          => $this->base() . '/d/' . $p->discussion_id . '/' . $p->number,
            'content'      => $p->content,
            'author'       => $p->user?->display_name,
            'authorId'     => (int) $p->user_id,
            'createdAt'    => optional($p->created_at)->toIso8601String(),
        ]);
    }

    public function userRegistered(Registered $event): void
    {
        $u = $event->user;

        $this->webhooks->fire('user.registered', [
            'id'        => (int) $u->id,
            'username'  => $u->username,
            'name'      => $u->display_name,
            'email'     => $u->email,
            'url'       => $this->base() . '/u/' . $u->username,
            'createdAt' => optional($u->joined_at)->toIso8601String(),
        ]);
    }

    private function base(): string
    {
        return rtrim($this->url->to('forum')->base(), '/');
    }
}
