<?php

namespace Ernestdefoe\Connect\Integration;

use Ernestdefoe\Connect\Http\SafeUrl;
use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Model\Hook;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Laminas\Diactoros\Response\JsonResponse;

/**
 * REST Hooks: an external service subscribes a target URL to an event under its
 * key, and unsubscribes it later by the id it got back.
 */
class Hooks
{
    /** POST /api/connect/hooks {event, targetUrl, zapId?}: idempotent per (key, event, url). */
    public function subscribe(ApiKey $key, array $body): JsonResponse
    {
        $event = (string) Arr::get($body, 'event', '');
        $target = (string) Arr::get($body, 'targetUrl', '');
        $zapId = Arr::get($body, 'zapId');

        if (! EventRegistry::exists($event)) {
            return Errors::json(422, 'unknown_event', $event);
        }

        // https only, and never an address inside the forum's own network.
        if (! SafeUrl::allowed($target)) {
            return Errors::json(422, 'invalid_target_url');
        }

        $hook = Hook::query()->firstOrNew([
            'api_key_id' => $key->id,
            'event' => $event,
            'target_url' => $target,
        ]);
        $hook->zap_id = $zapId ? (string) $zapId : $hook->zap_id;
        $hook->save();

        return new JsonResponse(['id' => (int) $hook->id, 'event' => $event], 201);
    }

    /** DELETE /api/connect/hooks/{hook}: only the key that made a hook can remove it. */
    public function unsubscribe(ApiKey $key, int $id): EmptyResponse
    {
        Hook::query()->whereKey($id)->where('api_key_id', $key->id)->delete();

        return new EmptyResponse(204);
    }
}
