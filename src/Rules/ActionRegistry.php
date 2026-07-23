<?php

namespace Ernestdefoe\Connect\Rules;

/**
 * Catalog of Rules actions — what an automation can DO when it fires. Each entry
 * declares its params and any optional Flarum dependency, so the admin UI can
 * build the right inputs and hide actions whose dependency isn't installed.
 */
class ActionRegistry
{
    /** key => [label, params[], ?requiresClass] */
    public const ACTIONS = [
        'reply'              => ['Post a reply',            ['content'], null],
        'add_tag'            => ['Add a tag',               ['tagId'],   \Flarum\Tags\Tag::class],
        'remove_tag'         => ['Remove a tag',            ['tagId'],   \Flarum\Tags\Tag::class],
        'add_to_group'       => ['Add user to a group',     ['groupId'], null],
        'remove_from_group'  => ['Remove user from a group', ['groupId'], null],
        'call_webhook'       => ['Send to a webhook URL',   ['url'],     null],
    ];

    /** Actions available on this install (optional-dep ones filtered out). */
    public static function available(): array
    {
        $out = [];
        foreach (self::ACTIONS as $key => [$label, $params, $requires]) {
            if ($requires && ! class_exists($requires)) {
                continue;
            }
            $out[] = ['key' => $key, 'label' => $label, 'params' => $params];
        }

        return $out;
    }

    public static function exists(string $key): bool
    {
        if (! array_key_exists($key, self::ACTIONS)) {
            return false;
        }
        $requires = self::ACTIONS[$key][2];

        return ! $requires || class_exists($requires);
    }
}
