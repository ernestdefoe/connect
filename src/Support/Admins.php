<?php

namespace Ernestdefoe\Connect\Support;

use Flarum\Group\Group;
use Flarum\User\User;

final class Admins
{
    /** The forum's first administrator: the fallback actor for keys and rules. */
    public static function first(): ?User
    {
        return User::query()
            ->whereHas('groups', fn ($q) => $q->whereKey(Group::ADMINISTRATOR_ID))
            ->orderBy('id')
            ->first();
    }
}
