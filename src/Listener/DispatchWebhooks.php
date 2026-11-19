<?php

namespace Ernestdefoe\Connect\Listener;

use Ernestdefoe\Connect\Rules\Engine as Rules;
use Ernestdefoe\Connect\Webhook\Dispatcher;
use Ernestdefoe\Connect\Webhook\Payload;
use Flarum\Database\AbstractModel;
use Flarum\Discussion\Event\Started;
use Flarum\Http\UrlGenerator;
use Flarum\Post\Event\Posted;
use Flarum\User\Event\Registered;
use Illuminate\Contracts\Events\Dispatcher as Events;

/**
 * Bridges Flarum's domain events to Connect trigger events (payloads shaped by
 * Payload). Every trigger fans out to BOTH external webhooks and the in-app
 * Rules engine.
 */
class DispatchWebhooks
{
    public function __construct(
        protected Dispatcher $webhooks,
        protected Rules $rules,
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
        $this->trigger('discussion.created', Payload::discussion($event->discussion, $this->base()), $event->discussion);
    }

    public function postPosted(Posted $event): void
    {
        // The first post of a discussion already fires discussion.created.
        if ($event->post->number <= 1) {
            return;
        }

        $this->trigger('post.created', Payload::post($event->post, $this->base()), $event->post);
    }

    public function userRegistered(Registered $event): void
    {
        $this->trigger('user.registered', Payload::user($event->user, $this->base()), $event->user);
    }

    /** Fire a trigger to external webhooks and the in-app Rules engine. */
    private function trigger(string $event, array $payload, AbstractModel $subject): void
    {
        $this->webhooks->fire($event, $payload, $subject);
        $this->rules->fire($event, $payload);
    }

    private function base(): string
    {
        return rtrim($this->url->to('forum')->base(), '/');
    }
}
