<?php

namespace Ernestdefoe\Connect\Webhook;

use Carbon\Carbon;
use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * Decides what a key's bound user is allowed to receive. A key acts as a forum
 * user, so a webhook or sample may only carry what that user could open in the
 * forum: nothing from a discussion, post or profile hidden from them, and a
 * member's email only when they may edit other members' credentials.
 */
final class Audience
{
    /** A suspended user's keys stop working until the suspension ends. */
    public static function suspended(User $user): bool
    {
        $until = $user->getAttribute('suspended_until');

        return $until !== null && Carbon::parse($until)->isFuture();
    }

    /** Whether $viewer may see $subject, by the forum's own visibility rules. */
    public static function canSee(User $viewer, AbstractModel $subject): bool
    {
        return $subject->newQuery()->whereVisibleTo($viewer)->whereKey($subject->getKey())->exists();
    }

    /** Whether $viewer may see $member's email address. */
    public static function canSeeEmail(User $viewer, User $member): bool
    {
        return $viewer->can('editCredentials', $member);
    }

    /**
     * The payload $viewer may receive for $subject, or null when they may not
     * see it at all. The `email` key is kept (as null) so the shape is stable.
     */
    public static function payloadFor(User $viewer, AbstractModel $subject, array $payload): ?array
    {
        if (self::suspended($viewer) || ! self::canSee($viewer, $subject)) {
            return null;
        }

        if ($subject instanceof User && array_key_exists('email', $payload) && ! self::canSeeEmail($viewer, $subject)) {
            $payload['email'] = null;
        }

        return $payload;
    }
}
