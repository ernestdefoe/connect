<?php

namespace Ernestdefoe\Connect\Webhook;

use Ernestdefoe\Connect\Model\Hook;
use Flarum\Database\AbstractModel;
use Illuminate\Contracts\Bus\Dispatcher as Bus;

/**
 * Fans a single fired event out to every subscribed target URL. Each delivery is
 * its own queued job, so a slow or dead endpoint never blocks the request that
 * triggered it (e.g. someone posting a discussion).
 *
 * A hook only receives what its key's user could see in the forum (see
 * Audience): one visibility check per distinct user, not per hook.
 */
class Dispatcher
{
    public function __construct(
        protected Bus $bus
    ) {
    }

    /**
     * @param string        $event   registry key, e.g. "discussion.created"
     * @param array         $payload the JSON body subscribers receive
     * @param AbstractModel $subject the discussion, post or user the event is about
     */
    public function fire(string $event, array $payload, AbstractModel $subject): void
    {
        if (! EventRegistry::exists($event)) {
            return;
        }

        $hooks = Hook::query()->where('event', $event)->with('apiKey.user')->get();

        /** @var array<int, ?array> $byUser payload each user may receive (null = nothing) */
        $byUser = [];

        foreach ($hooks as $hook) {
            $key  = $hook->apiKey;
            $user = $key?->user;

            if (! $user || ! $key->hasScope('read')) {
                continue;
            }

            if (! array_key_exists($user->id, $byUser)) {
                $byUser[$user->id] = Audience::payloadFor($user, $subject, $payload);
            }

            if ($byUser[$user->id] === null) {
                continue;
            }

            $this->bus->dispatch(new SendWebhook(
                $hook->id,
                $hook->target_url,
                $key->secret,
                $event,
                $byUser[$user->id]
            ));
        }
    }
}
