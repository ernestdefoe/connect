<?php

namespace Ernestdefoe\Connect\Support;

use Flarum\Discussion\Discussion;

/**
 * Whether flarum/tags is enabled. Tags adds the discussion `tags` relation
 * through Extend\Model only while it is enabled, so asking Eloquent for that
 * relation answers for the running forum (installed-but-disabled counts as off)
 * without loading any Tags class.
 */
final class Tags
{
    public static function enabled(): bool
    {
        return (new Discussion())->isRelation('tags');
    }
}
