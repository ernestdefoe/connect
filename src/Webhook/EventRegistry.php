<?php

namespace Ernestdefoe\Connect\Webhook;

/**
 * The catalog of trigger events Connect can emit. Kept in one place so the
 * subscribe endpoint can validate event names, the Zapier app / admin UI can
 * list them, and the sample endpoint can key off them. Add an event here, emit
 * it from a listener, and it's instantly available to every subscriber.
 */
class EventRegistry
{
    /** key => [label, scope] */
    public const EVENTS = [
        'discussion.created' => ['New discussion', 'read'],
        'post.created'       => ['New reply',      'read'],
        'user.registered'    => ['New user',       'read'],
    ];

    public static function all(): array
    {
        return array_map(
            fn (array $meta, string $key) => ['key' => $key, 'label' => $meta[0], 'scope' => $meta[1]],
            self::EVENTS,
            array_keys(self::EVENTS)
        );
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::EVENTS);
    }
}
