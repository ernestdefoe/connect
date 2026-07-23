<?php

namespace Ernestdefoe\Connect\Webhook;

use Ernestdefoe\Connect\Model\Hook;
use Illuminate\Contracts\Bus\Dispatcher as Bus;

/**
 * Fans a single fired event out to every subscribed target URL. Each delivery is
 * its own queued job, so a slow or dead endpoint never blocks the request that
 * triggered it (e.g. someone posting a discussion).
 */
class Dispatcher
{
    public function __construct(
        protected Bus $bus
    ) {
    }

    /**
     * @param string $event   registry key, e.g. "discussion.created"
     * @param array  $payload the JSON body subscribers receive
     */
    public function fire(string $event, array $payload): void
    {
        if (! EventRegistry::exists($event)) {
            return;
        }

        Hook::query()->where('event', $event)->with('apiKey')->get()
            ->each(function (Hook $hook) use ($event, $payload) {
                if (! $hook->apiKey) {
                    return;
                }
                $this->bus->dispatch(new SendWebhook(
                    $hook->id,
                    $hook->target_url,
                    $hook->apiKey->secret,
                    $event,
                    $payload
                ));
            });
    }
}
