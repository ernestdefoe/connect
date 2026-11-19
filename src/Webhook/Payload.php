<?php

namespace Ernestdefoe\Connect\Webhook;

use Ernestdefoe\Connect\Support\Tags;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\User\User;
use Illuminate\Support\Collection;

/**
 * The flat body each trigger event carries. The live webhooks and the Zapier
 * sample endpoint both build it here, because Zapier requires the sample and
 * the live payload to have the same shape.
 */
final class Payload
{
    public static function discussion(Discussion $d, string $base): array
    {
        return [
            'id' => (int) $d->id,
            'title' => $d->title,
            'slug' => $d->slug,
            'url' => $base.'/d/'.$d->id.'-'.$d->slug,
            'author' => $d->user?->display_name,
            'authorId' => (int) $d->user_id,
            'tagList' => self::tagList($d),
            'createdAt' => $d->created_at?->toIso8601String(),
        ];
    }

    public static function post(Post $p, string $base): array
    {
        return [
            'id' => (int) $p->id,
            'discussionId' => (int) $p->discussion_id,
            'url' => $base.'/d/'.$p->discussion_id.'/'.(int) $p->number,
            'content' => $p->content,
            'author' => $p->user?->display_name,
            'authorId' => (int) $p->user_id,
            'createdAt' => $p->created_at?->toIso8601String(),
        ];
    }

    /** Includes the email: Audience removes it for a receiver who may not see it. */
    public static function user(User $u, string $base): array
    {
        return [
            'id' => (int) $u->id,
            'username' => $u->username,
            'name' => $u->display_name,
            'email' => $u->email,
            'url' => $base.'/u/'.$u->username,
            'createdAt' => $u->joined_at?->toIso8601String(),
        ];
    }

    /** Comma-joined tag slugs (empty without flarum/tags), so conditions can match on tags. */
    private static function tagList(Discussion $d): string
    {
        if (! Tags::enabled()) {
            return '';
        }

        $tags = $d->getAttribute('tags');

        return $tags instanceof Collection ? $tags->pluck('slug')->implode(',') : '';
    }
}
