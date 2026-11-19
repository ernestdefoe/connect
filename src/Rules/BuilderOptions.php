<?php

namespace Ernestdefoe\Connect\Rules;

use Ernestdefoe\Connect\Support\Tags;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Flarum\Group\Group;

/**
 * Everything the admin Rules builder needs for its dropdowns: triggers,
 * actions, operators, and the groups and tags an action can target here.
 */
final class BuilderOptions
{
    public static function all(): array
    {
        return [
            'events' => EventRegistry::all(),
            'actions' => ActionRegistry::available(),
            'operators' => Conditions::OPERATORS,
            // Guest and Member are implied groups nobody is added to.
            'groups' => Group::query()->whereNotIn('id', [Group::GUEST_ID, Group::MEMBER_ID])->get(['id', 'name_singular'])
                ->map(fn (Group $g) => ['id' => (int) $g->id, 'name' => $g->name_singular])->values()->all(),
            'tags' => Tags::enabled()
                ? \Flarum\Tags\Tag::query()->get(['id', 'name'])->map(fn ($t) => ['id' => (int) $t->id, 'name' => $t->name])->values()->all()
                : [],
        ];
    }
}
