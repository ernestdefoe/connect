<?php

namespace Ernestdefoe\Connect\Rules;

use Flarum\Api\Client as ApiClient;
use Flarum\Discussion\Discussion;
use Flarum\User\User;
use GuzzleHttp\Client as Http;

/**
 * Executes a rule's actions against a fired event. The target (which discussion,
 * which user) is resolved from the event + payload, so the same action ("add a
 * tag") works whether the trigger was a new discussion or a new reply.
 */
class ActionRunner
{
    public function __construct(
        protected ApiClient $api
    ) {
    }

    /** @param array $actions [{type, ...params}] */
    public function run(array $actions, string $event, array $payload, User $actor): void
    {
        $ctx = $this->context($event, $payload);

        foreach ($actions as $action) {
            $action = (array) $action;
            $type   = $action['type'] ?? '';

            if (! ActionRegistry::exists($type)) {
                continue;
            }

            try {
                $this->one($type, $action, $ctx, $payload, $actor);
            } catch (\Throwable $e) {
                // One failed action shouldn't abort the rest of the rule.
            }
        }
    }

    private function one(string $type, array $a, array $ctx, array $payload, User $actor): void
    {
        switch ($type) {
            case 'reply':
                if ($ctx['discussionId'] && trim((string) ($a['content'] ?? '')) !== '') {
                    $this->api->withActor($actor)->withBody(['data' => [
                        'type' => 'posts',
                        'attributes' => ['content' => (string) $a['content']],
                        'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => (string) $ctx['discussionId']]]],
                    ]])->post('/posts');
                }
                break;

            case 'add_tag':
            case 'remove_tag':
                if ($ctx['discussionId'] && ($tagId = (int) ($a['tagId'] ?? 0))) {
                    $d = Discussion::query()->find($ctx['discussionId']);
                    if ($d && method_exists($d, 'tags')) {
                        $type === 'add_tag' ? $d->tags()->syncWithoutDetaching([$tagId]) : $d->tags()->detach($tagId);
                    }
                }
                break;

            case 'add_to_group':
            case 'remove_from_group':
                if ($ctx['userId'] && ($groupId = (int) ($a['groupId'] ?? 0))) {
                    $u = User::query()->find($ctx['userId']);
                    if ($u) {
                        $type === 'add_to_group' ? $u->groups()->syncWithoutDetaching([$groupId]) : $u->groups()->detach($groupId);
                    }
                }
                break;

            case 'call_webhook':
                if (($url = (string) ($a['url'] ?? '')) && str_starts_with($url, 'https://')) {
                    (new Http())->post($url, [
                        'json'            => ['event' => $event, 'data' => $payload],
                        'timeout'         => 12,
                        'connect_timeout' => 6,
                        'http_errors'     => false,
                    ]);
                }
                break;
        }
    }

    /** Resolve the discussion/user this event is "about". */
    private function context(string $event, array $payload): array
    {
        $isPost = str_starts_with($event, 'post');
        $isUser = str_starts_with($event, 'user');

        return [
            'discussionId' => $isPost ? (int) ($payload['discussionId'] ?? 0) : (int) ($payload['id'] ?? 0),
            'userId'       => $isUser ? (int) ($payload['id'] ?? 0) : (int) ($payload['authorId'] ?? 0),
        ];
    }
}
