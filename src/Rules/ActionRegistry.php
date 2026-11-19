<?php

namespace Ernestdefoe\Connect\Rules;

use Ernestdefoe\Connect\Support\Tags;

/**
 * Catalog of Rules actions — what an automation can DO when it fires. Each entry
 * declares its params and whether it needs flarum/tags, so the admin UI can
 * build the right inputs and hide the tag actions on a forum without Tags.
 */
class ActionRegistry
{
    /** key => [label, params[], needs flarum/tags] */
    public const ACTIONS = [
        'reply' => ['Post a reply',            ['content'], false],
        'add_tag' => ['Add a tag',               ['tagId'],   true],
        'remove_tag' => ['Remove a tag',            ['tagId'],   true],
        'add_to_group' => ['Add user to a group',     ['groupId'], false],
        'remove_from_group' => ['Remove user from a group', ['groupId'], false],
        'call_webhook' => ['Send to a webhook URL',   ['url'],     false],
    ];

    /** Actions available on this install. */
    public static function available(): array
    {
        $out = [];
        foreach (self::ACTIONS as $key => [$label, $params]) {
            if (self::exists($key)) {
                $out[] = ['key' => $key, 'label' => $label, 'params' => $params];
            }
        }

        return $out;
    }

    public static function exists(string $key): bool
    {
        return array_key_exists($key, self::ACTIONS) && (! self::ACTIONS[$key][2] || Tags::enabled());
    }

    /** Keep only available actions, each with just its own params, as strings. */
    public static function clean(array $in): array
    {
        $out = [];
        foreach ($in as $a) {
            $a = (array) $a;
            $type = (string) ($a['type'] ?? '');
            if (! self::exists($type)) {
                continue;
            }
            $clean = ['type' => $type];
            foreach (self::ACTIONS[$type][1] as $param) {
                $clean[$param] = (string) ($a[$param] ?? '');
            }
            $out[] = $clean;
        }

        return $out;
    }
}
