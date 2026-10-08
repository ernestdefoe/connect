<?php

namespace Ernestdefoe\Connect\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use GuzzleHttp\Client as Http;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\Attributes\Test;

class ConnectTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    private const ADMIN_KEY = 'ck_admin_key_for_tests_0000000000000000000000000000';
    private const MEMBER_KEY = 'ck_member_key_for_tests_000000000000000000000000000';
    private const READ_KEY = 'ck_read_only_key_for_tests_000000000000000000000000';
    private const WRITE_KEY = 'ck_write_only_key_for_tests_00000000000000000000000';

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-connect');

        $now = Carbon::now();

        $this->prepareDatabase([
            User::class => [$this->normalUser()],
            Tag::class => [
                ['id' => 1, 'name' => 'Sales', 'slug' => 'sales', 'position' => 0],
                ['id' => 2, 'name' => 'Archive', 'slug' => 'archive', 'position' => 1],
                ['id' => 3, 'name' => 'Staff', 'slug' => 'staff', 'position' => 2, 'is_restricted' => true],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'Public', 'created_at' => $now, 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 1],
                ['id' => 2, 'title' => 'Hidden', 'created_at' => $now, 'user_id' => 1, 'first_post_id' => 2, 'comment_count' => 1, 'hidden_at' => $now],
                ['id' => 3, 'title' => 'Staff only', 'created_at' => $now, 'user_id' => 1, 'first_post_id' => 3, 'comment_count' => 1],
            ],
            Post::class => [
                ['id' => 1, 'discussion_id' => 1, 'number' => 1, 'created_at' => $now->copy()->subHour(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Hello</p></t>'],
                ['id' => 2, 'discussion_id' => 2, 'number' => 1, 'created_at' => $now, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Secret</p></t>'],
                ['id' => 3, 'discussion_id' => 3, 'number' => 1, 'created_at' => $now, 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Staff</p></t>'],
            ],
            'discussion_tag' => [['discussion_id' => 1, 'tag_id' => 1], ['discussion_id' => 2, 'tag_id' => 1], ['discussion_id' => 3, 'tag_id' => 3]],
            'connect_api_keys' => [
                ['id' => 1, 'label' => 'Admin', 'token' => self::ADMIN_KEY, 'secret' => 'cs_a', 'user_id' => 1, 'scopes' => json_encode([])],
                ['id' => 2, 'label' => 'Member', 'token' => self::MEMBER_KEY, 'secret' => 'cs_m', 'user_id' => 2, 'scopes' => json_encode([])],
                ['id' => 3, 'label' => 'Read', 'token' => self::READ_KEY, 'secret' => 'cs_r', 'user_id' => 2, 'scopes' => json_encode(['read'])],
                ['id' => 4, 'label' => 'Write', 'token' => self::WRITE_KEY, 'secret' => 'cs_w', 'user_id' => 2, 'scopes' => json_encode(['write'])],
            ],
        ]);
    }

    private function withKey(string $method, string $path, string $key, array $json = []): array
    {
        $request = $this->request($method, $path, $json ? ['json' => $json] : [])->withHeader('Authorization', 'Bearer '.$key);
        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    private function asUser(string $method, string $path, ?int $actor, array $json = []): array
    {
        $request = $this->request($method, $path, ($actor ? ['authenticatedAs' => $actor] : []) + ($json ? ['json' => $json] : []));

        if (! $actor && $method !== 'GET') {
            $request = $this->requestWithCsrfToken($request);
        }

        $response = $this->send($request);

        return [$response->getStatusCode(), json_decode((string) $response->getBody(), true)];
    }

    public static function adminRoutes(): array
    {
        return [
            'list keys' => ['GET', '/api/connect/keys'],
            'create key' => ['POST', '/api/connect/keys'],
            'delete key' => ['DELETE', '/api/connect/keys/2'],
            'subscriptions' => ['GET', '/api/connect/subscriptions'],
            'events' => ['GET', '/api/connect/events'],
            'meta' => ['GET', '/api/connect/meta'],
            'list rules' => ['GET', '/api/connect/rules'],
            'create rule' => ['POST', '/api/connect/rules'],
        ];
    }

    #[Test]
    #[DataProvider('adminRoutes')]
    public function the_admin_routes_refuse_guests_members_and_keys(string $method, string $path)
    {
        [$status] = $this->asUser($method, $path, null);
        $this->assertSame(403, $status, 'A guest');

        [$status] = $this->asUser($method, $path, 2, ['data' => ['label' => 'Mine', 'event' => 'discussion.created']]);
        $this->assertSame(403, $status, 'A member');

        // Even the admin's own key is not honoured off Connect's integration routes.
        [$status] = $this->withKey($method, $path, self::ADMIN_KEY, ['data' => ['label' => 'Via key']]);
        $this->assertContains($status, [400, 403], 'A key');

        $this->assertSame(4, $this->database()->table('connect_api_keys')->count());
    }

    #[Test]
    public function a_key_authenticates_as_its_user_on_the_integration_routes_only()
    {
        [$status, $body] = $this->withKey('GET', '/api/connect/me', self::MEMBER_KEY);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame('normal', $body['data']['attributes']['user']);

        [$status] = $this->withKey('GET', '/api/connect/me', 'ck_not_a_real_key');
        $this->assertSame(401, $status);

        [$status] = $this->asUser('GET', '/api/connect/me', null);
        $this->assertSame(401, $status);

        // A core route with a key is handled as if the key were not there.
        [$status] = $this->withKey('POST', '/api/discussions', self::ADMIN_KEY, ['data' => ['type' => 'discussions', 'attributes' => ['title' => 'x', 'content' => 'y']]]);
        $this->assertSame(400, $status, 'No CSRF bypass, no actor');
    }

    #[Test]
    public function each_route_needs_its_scope()
    {
        [$status, $body] = $this->withKey('POST', '/api/connect/actions/discussions', self::READ_KEY, ['title' => 'T', 'content' => 'C']);
        $this->assertSame([403, 'insufficient_scope'], [$status, $body['errors'][0]['code'] ?? null]);

        [$status, $body] = $this->withKey('GET', '/api/connect/discussions', self::WRITE_KEY);
        $this->assertSame([403, 'insufficient_scope'], [$status, $body['errors'][0]['code'] ?? null]);
    }

    #[Test]
    public function a_key_lists_only_what_its_user_may_see()
    {
        [$status, $body] = $this->withKey('GET', '/api/connect/discussions', self::MEMBER_KEY);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame([1], array_column($body, 'id'), 'Not the restricted tag, nor the hidden discussion');

        [, $body] = $this->withKey('GET', '/api/connect/discussions', self::ADMIN_KEY);
        $this->assertEqualsCanonicalizing([1, 3], array_column($body, 'id'));
    }

    #[Test]
    public function a_write_key_starts_a_discussion_and_replies_as_its_user()
    {
        [$status, $body] = $this->withKey('POST', '/api/connect/actions/discussions', self::MEMBER_KEY, ['title' => 'From Zapier', 'content' => 'Hello there', 'tags' => [1]]);
        $this->assertSame(201, $status, json_encode($body));
        $id = (int) $body['data']['id'];
        $this->assertSame(2, (int) $this->database()->table('discussions')->where('id', $id)->value('user_id'));

        // As the admin: a second post by the same member seconds later is
        // refused by Flarum's own flood guard, which is not what this tests.
        [$status, $body] = $this->withKey('POST', '/api/connect/actions/posts', self::ADMIN_KEY, ['discussionId' => $id, 'content' => 'A reply']);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame(1, (int) $this->database()->table('posts')->where('discussion_id', $id)->orderByDesc('number')->value('user_id'));
        $this->assertSame(2, $this->database()->table('posts')->where('discussion_id', $id)->count());

        [$status] = $this->withKey('POST', '/api/connect/actions/discussions', self::MEMBER_KEY, ['title' => '', 'content' => '']);
        $this->assertSame(422, $status);
    }

    #[Test]
    public function a_hook_must_target_a_public_https_url_and_only_its_key_can_remove_it()
    {
        [$status, $body] = $this->withKey('POST', '/api/connect/hooks', self::MEMBER_KEY, ['event' => 'discussion.created', 'targetUrl' => 'https://127.0.0.1/hook']);
        $this->assertSame([422, 'invalid_target_url'], [$status, $body['errors'][0]['code'] ?? null]);

        [$status] = $this->withKey('POST', '/api/connect/hooks', self::MEMBER_KEY, ['event' => 'discussion.created', 'targetUrl' => 'http://93.184.216.34/hook']);
        $this->assertSame(422, $status, 'Plain http is refused');

        [$status] = $this->withKey('POST', '/api/connect/hooks', self::MEMBER_KEY, ['event' => 'nope', 'targetUrl' => 'https://93.184.216.34/hook']);
        $this->assertSame(422, $status);

        [$status, $body] = $this->withKey('POST', '/api/connect/hooks', self::MEMBER_KEY, ['event' => 'discussion.created', 'targetUrl' => 'https://93.184.216.34/hook']);
        $this->assertSame(201, $status, json_encode($body));
        $hook = $body['id'];

        $this->withKey('DELETE', "/api/connect/hooks/$hook", self::ADMIN_KEY);
        $this->assertSame(1, $this->database()->table('connect_hooks')->count(), 'Another key cannot remove it');

        [$status] = $this->withKey('DELETE', "/api/connect/hooks/$hook", self::MEMBER_KEY);
        $this->assertSame(204, $status);
        $this->assertSame(0, $this->database()->table('connect_hooks')->count());
    }

    #[Test]
    public function a_sample_discussion_carries_its_tags()
    {
        [$status, $body] = $this->withKey('GET', '/api/connect/samples/discussion.created', self::MEMBER_KEY);

        $this->assertSame(200, $status, json_encode($body));
        $this->assertSame([1], array_column($body, 'id'));
        $this->assertSame('sales', $body[0]['tagList']);
    }

    #[Test]
    public function an_admin_creates_a_key_and_sees_it_listed()
    {
        [$status, $body] = $this->asUser('POST', '/api/connect/keys', 1, ['data' => ['label' => 'Zapier', 'userId' => 2, 'scopes' => ['read']]]);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertStringStartsWith('ck_', $body['data']['token']);
        $this->assertSame('normal', $body['data']['user']);

        [$status, $body] = $this->asUser('GET', '/api/connect/keys', 1);
        $this->assertSame(200, $status);
        $this->assertCount(5, $body['data']);
    }

    #[Test]
    public function a_rule_matches_a_new_discussion_on_its_tags()
    {
        // add_to_group, not add_tag: on discussion.created core saves the
        // discussion's tags after the event, so a tag added by a rule running
        // on the sync queue would be overwritten (Flarum's order, not Connect's).
        $this->prepareDatabase(['connect_rules' => [
            ['id' => 1, 'name' => 'Sellers', 'event' => 'discussion.created', 'enabled' => true, 'match' => 'all', 'position' => 0, 'run_as_user_id' => 1, 'runs' => 0,
                'conditions' => json_encode([['field' => 'tagList', 'op' => 'contains', 'value' => 'sales']]),
                'actions' => json_encode([['type' => 'add_to_group', 'groupId' => 4]])],
        ]]);

        [$status, $body] = $this->withKey('POST', '/api/connect/actions/discussions', self::MEMBER_KEY, ['title' => 'For sale', 'content' => 'A bike', 'tags' => [1]]);
        $this->assertSame(201, $status, json_encode($body));

        $this->assertSame(1, (int) $this->database()->table('connect_rules')->where('id', 1)->value('runs'));
        $this->assertTrue($this->database()->table('group_user')->where('user_id', 2)->where('group_id', 4)->exists(), 'The tag condition matched');
    }

    #[Test]
    public function a_rule_tags_the_discussion_of_a_new_reply()
    {
        $this->prepareDatabase(['connect_rules' => [
            ['id' => 1, 'name' => 'Archive on reply', 'event' => 'post.created', 'enabled' => true, 'match' => 'all', 'position' => 0, 'run_as_user_id' => 1, 'runs' => 0,
                'conditions' => json_encode([]), 'actions' => json_encode([['type' => 'add_tag', 'tagId' => 2]])],
        ]]);

        [$status, $body] = $this->withKey('POST', '/api/connect/actions/posts', self::MEMBER_KEY, ['discussionId' => 1, 'content' => 'Still available?']);
        $this->assertSame(201, $status, json_encode($body));

        $tags = $this->database()->table('discussion_tag')->where('discussion_id', 1)->orderBy('tag_id')->pluck('tag_id')->map(fn ($t) => (int) $t)->all();
        $this->assertSame([1, 2], $tags);
    }

    #[Test]
    public function a_rule_calls_a_webhook_with_the_event_it_fired_on()
    {
        $this->prepareDatabase(['connect_rules' => [
            ['id' => 1, 'name' => 'Ping', 'event' => 'post.created', 'enabled' => true, 'match' => 'all', 'position' => 0, 'run_as_user_id' => 1, 'runs' => 0,
                'conditions' => json_encode([]), 'actions' => json_encode([['type' => 'call_webhook', 'url' => 'https://93.184.216.34/hook']])],
        ]]);

        // Record what would be sent instead of sending it.
        $sent = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200)]));
        $stack->push(Middleware::history($sent));
        $this->app()->getContainer()->instance(Http::class, new Http(['handler' => $stack]));

        [$status, $body] = $this->withKey('POST', '/api/connect/actions/posts', self::MEMBER_KEY, ['discussionId' => 1, 'content' => 'Ping me']);
        $this->assertSame(201, $status, json_encode($body));

        $this->assertCount(1, $sent);
        $this->assertSame('https://93.184.216.34/hook', (string) $sent[0]['request']->getUri());
        $payload = json_decode((string) $sent[0]['request']->getBody(), true);
        $this->assertSame('post.created', $payload['event']);
        $this->assertSame(1, $payload['data']['discussionId']);
    }
}
