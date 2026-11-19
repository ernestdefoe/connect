<?php

namespace Ernestdefoe\Connect\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use GuzzleHttp\Client as Http;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Middleware;
use GuzzleHttp\Psr7\Response;
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
            'list keys' => ['GET', '/api/connect-keys'],
            'create key' => ['POST', '/api/connect-keys'],
            'delete key' => ['DELETE', '/api/connect-keys/2'],
            'subscriptions' => ['GET', '/api/connect-subscriptions'],
            'meta' => ['GET', '/api/connect/meta'],
            'list rules' => ['GET', '/api/connect-rules'],
            'create rule' => ['POST', '/api/connect-rules'],
            'update rule' => ['PATCH', '/api/connect-rules/1'],
            'delete rule' => ['DELETE', '/api/connect-rules/1'],
        ];
    }

    #[Test]
    #[DataProvider('adminRoutes')]
    public function the_admin_routes_refuse_guests_members_and_keys(string $method, string $path)
    {
        $this->prepareDatabase(['connect_rules' => [
            ['id' => 1, 'name' => 'Kept', 'event' => 'post.created', 'enabled' => true, 'match' => 'all', 'position' => 0, 'run_as_user_id' => 1, 'runs' => 0, 'conditions' => '[]', 'actions' => '[]'],
        ]]);
        $body = ['data' => ['type' => str_contains($path, 'rules') ? 'connect-rules' : 'connect-keys', 'attributes' => ['label' => 'Mine', 'name' => 'Mine', 'event' => 'discussion.created', 'enabled' => false]]];

        [$status] = $this->asUser($method, $path, null, $body);
        $this->assertContains($status, [401, 403], 'A guest');

        [$status] = $this->asUser($method, $path, 2, $body);
        $this->assertSame(403, $status, 'A member');

        // Even the admin's own key is not honoured off Connect's integration routes.
        [$status] = $this->withKey($method, $path, self::ADMIN_KEY, $body);
        $this->assertContains($status, [400, 401, 403], 'A key');

        $this->assertSame(4, $this->database()->table('connect_api_keys')->count());
        $this->assertSame(['Kept', 1], [
            $this->database()->table('connect_rules')->value('name'),
            (int) $this->database()->table('connect_rules')->value('enabled'),
        ]);
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

        // Signed in, but without a key: the integration routes still refuse.
        [$status, $body] = $this->asUser('GET', '/api/connect/me', 2);
        $this->assertSame([401, 'not_authenticated'], [$status, $body['errors'][0]['code'] ?? null]);

        [$status, $body] = $this->withKey('GET', '/api/connect/samples/nothing.happened', self::MEMBER_KEY);
        $this->assertSame([404, 'unknown_event'], [$status, $body['errors'][0]['code'] ?? null]);

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
        [$status, $body] = $this->asUser('POST', '/api/connect-keys', 1, ['data' => ['type' => 'connect-keys', 'attributes' => ['label' => 'Zapier', 'userId' => 2, 'scopes' => ['read', 'admin']]]]);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertStringStartsWith('ck_', $body['data']['attributes']['token']);
        $this->assertStringStartsWith('cs_', $body['data']['attributes']['secret']);
        $this->assertSame('normal', $body['data']['attributes']['user']);
        $this->assertSame(['read'], $body['data']['attributes']['scopes'], 'Unknown scopes are dropped');

        [$status, $body] = $this->asUser('POST', '/api/connect-keys', 1, ['data' => ['type' => 'connect-keys', 'attributes' => ['label' => 'Mine']]]);
        $this->assertSame(201, $status, json_encode($body));
        $this->assertSame('admin', $body['data']['attributes']['user'], 'A key acts as its creator by default');
        $this->assertSame(['*'], $body['data']['attributes']['scopes']);

        [$status, $body] = $this->asUser('POST', '/api/connect-keys', 1, ['data' => ['type' => 'connect-keys', 'attributes' => ['label' => 'Nobody', 'userId' => 999]]]);
        $this->assertSame(422, $status, 'The user must exist');

        [$status, $body] = $this->asUser('GET', '/api/connect-keys', 1);
        $this->assertSame(200, $status);
        $this->assertCount(6, $body['data']);
    }

    #[Test]
    public function revoking_a_key_removes_its_subscriptions()
    {
        $this->prepareDatabase(['connect_hooks' => [
            ['id' => 1, 'api_key_id' => 3, 'event' => 'post.created', 'target_url' => 'https://93.184.216.34/zap', 'failures' => 0],
            ['id' => 2, 'api_key_id' => 1, 'event' => 'post.created', 'target_url' => 'https://93.184.216.34/other', 'failures' => 0],
        ]]);

        [$status, $body] = $this->asUser('GET', '/api/connect-subscriptions', 1);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertEqualsCanonicalizing(['Read', 'Admin'], array_column(array_column($body['data'], 'attributes'), 'keyLabel'));

        [$status] = $this->asUser('DELETE', '/api/connect-keys/3', 1);
        $this->assertSame(204, $status);
        $this->assertSame([2], $this->database()->table('connect_hooks')->pluck('id')->all());
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
        $this->assertSame(1, (int) $this->database()->table('tags')->where('id', 2)->value('discussion_count'), "Tags' own count follows");
    }

    #[Test]
    public function a_rule_action_its_actor_may_not_do_is_refused_and_logged()
    {
        // The rule runs as a plain member, who may not move people between groups.
        $this->prepareDatabase(['connect_rules' => [
            ['id' => 1, 'name' => 'Promote', 'event' => 'post.created', 'enabled' => true, 'match' => 'all', 'position' => 0, 'run_as_user_id' => 2, 'runs' => 0,
                'conditions' => json_encode([]), 'actions' => json_encode([['type' => 'add_to_group', 'groupId' => 4]])],
        ]]);
        $log = new \Monolog\Handler\TestHandler();
        $this->app()->getContainer()->instance(\Psr\Log\LoggerInterface::class, new \Monolog\Logger('test', [$log]));

        [$status, $body] = $this->withKey('POST', '/api/connect/actions/posts', self::MEMBER_KEY, ['discussionId' => 1, 'content' => 'Promote me']);
        $this->assertSame(201, $status, json_encode($body));

        $this->assertFalse($this->database()->table('group_user')->where('user_id', 2)->where('group_id', 4)->exists());
        $this->assertTrue($log->hasWarningThatContains('Rule action add_to_group failed on post.created: PATCH /users/2 answered 403'));
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

    #[Test]
    public function a_reply_rule_does_not_trigger_itself()
    {
        // The rule's own reply is a new post: it must not run the rule again.
        $this->prepareDatabase(['connect_rules' => [
            ['id' => 1, 'name' => 'Thanks', 'event' => 'post.created', 'enabled' => true, 'match' => 'all', 'position' => 0, 'run_as_user_id' => 1, 'runs' => 0,
                'conditions' => json_encode([]), 'actions' => json_encode([['type' => 'reply', 'content' => 'Thanks for replying']])],
        ]]);

        [$status, $body] = $this->withKey('POST', '/api/connect/actions/posts', self::MEMBER_KEY, ['discussionId' => 1, 'content' => 'Hi']);
        $this->assertSame(201, $status, json_encode($body));

        $this->assertSame(1, (int) $this->database()->table('connect_rules')->where('id', 1)->value('runs'));
        $this->assertSame(3, $this->database()->table('posts')->where('discussion_id', 1)->count(), 'Original post, the reply, and exactly one rule reply');
    }

    #[Test]
    public function a_rule_with_a_broken_pattern_is_not_saved()
    {
        $rule = ['name' => 'Bad', 'event' => 'post.created', 'conditions' => [['field' => 'content', 'op' => 'matches', 'value' => '(unclosed']]];

        [$status, $body] = $this->asUser('POST', '/api/connect-rules', 1, ['data' => ['type' => 'connect-rules', 'attributes' => $rule]]);
        $this->assertSame(422, $status, json_encode($body));
        $this->assertSame('/data/attributes/conditions', $body['errors'][0]['source']['pointer']);
        // The test forum loads no translations, so the key comes back (the_api_strings_are_translated checks it).
        $this->assertSame('ernestdefoe-connect.api.invalid_pattern', $body['errors'][0]['detail']);

        $rule['conditions'][0]['value'] = '^(sale|wanted)/\\d+';
        [$status, $body] = $this->asUser('POST', '/api/connect-rules', 1, ['data' => ['type' => 'connect-rules', 'attributes' => $rule]]);
        $this->assertSame(201, $status, json_encode($body));
    }

    #[Test]
    public function an_admin_builds_a_rule_and_only_known_parts_are_kept()
    {
        $rule = ['name' => '  ', 'event' => 'discussion.created', 'match' => 'any',
            'conditions' => [['field' => 'title', 'op' => 'contains', 'value' => 'Sale'], ['field' => 'title', 'op' => 'explode'], ['op' => 'equals']],
            'actions' => [['type' => 'add_tag', 'tagId' => 2, 'extra' => 'x'], ['type' => 'drop_everything']]];

        [$status, $body] = $this->asUser('POST', '/api/connect-rules', 1, ['data' => ['type' => 'connect-rules', 'attributes' => $rule]]);
        $this->assertSame(201, $status, json_encode($body));
        $attributes = $body['data']['attributes'];
        $this->assertSame('ernestdefoe-connect.api.untitled_rule', $attributes['name']);
        $this->assertSame([['field' => 'title', 'op' => 'contains', 'value' => 'Sale']], $attributes['conditions']);
        $this->assertSame([['type' => 'add_tag', 'tagId' => '2']], $attributes['actions']);
        $this->assertSame(1, $attributes['runAsUserId'], 'A rule runs as its creator by default');

        $id = $body['data']['id'];
        [$status, $body] = $this->asUser('PATCH', "/api/connect-rules/$id", 1, ['data' => ['type' => 'connect-rules', 'id' => $id, 'attributes' => ['enabled' => false]]]);
        $this->assertSame(200, $status, json_encode($body));
        $this->assertFalse($body['data']['attributes']['enabled']);
        $this->assertSame('any', $body['data']['attributes']['match'], 'An update keeps what it does not send');

        [$status, $body] = $this->asUser('PATCH', "/api/connect-rules/$id", 1, ['data' => ['type' => 'connect-rules', 'id' => $id, 'attributes' => ['event' => 'nothing.happened']]]);
        $this->assertSame(422, $status, 'Only a known trigger event');

        [$status] = $this->asUser('DELETE', "/api/connect-rules/$id", 1);
        $this->assertSame(204, $status);
        $this->assertSame(0, $this->database()->table('connect_rules')->count());
    }

    #[Test]
    public function a_failed_rule_action_is_logged()
    {
        $this->prepareDatabase(['connect_rules' => [
            ['id' => 1, 'name' => 'Ping', 'event' => 'post.created', 'enabled' => true, 'match' => 'all', 'position' => 0, 'run_as_user_id' => 1, 'runs' => 0,
                'conditions' => json_encode([]), 'actions' => json_encode([['type' => 'call_webhook', 'url' => 'https://93.184.216.34/hook'], ['type' => 'add_tag', 'tagId' => 2]])],
        ]]);

        $stack = HandlerStack::create(new MockHandler([new \RuntimeException('Connection refused')]));
        $this->app()->getContainer()->instance(Http::class, new Http(['handler' => $stack]));
        $log = new \Monolog\Handler\TestHandler();
        $this->app()->getContainer()->instance(\Psr\Log\LoggerInterface::class, new \Monolog\Logger('test', [$log]));

        [$status, $body] = $this->withKey('POST', '/api/connect/actions/posts', self::MEMBER_KEY, ['discussionId' => 1, 'content' => 'Ping me']);
        $this->assertSame(201, $status, json_encode($body));

        $this->assertTrue($log->hasWarningThatContains('Rule action call_webhook failed on post.created: Connection refused'));
        $this->assertTrue($this->database()->table('discussion_tag')->where('discussion_id', 1)->where('tag_id', 2)->exists(), 'The next action still ran');
        $this->assertSame(1, (int) $this->database()->table('connect_rules')->where('id', 1)->value('runs'));
    }

    #[Test]
    public function samples_load_authors_and_tags_in_a_fixed_number_of_queries()
    {
        $this->app();
        $queries = 0;
        $this->database()->listen(function () use (&$queries) {
            $queries++;
        });
        $count = function (string $event) use (&$queries): int {
            $queries = 0;
            [$status, $body] = $this->withKey('GET', "/api/connect/samples/$event", self::ADMIN_KEY);
            $this->assertSame(200, $status, json_encode($body));

            return $queries;
        };
        // One reply, so both samples start with one author to load.
        $this->database()->table('posts')->insert(['id' => 20, 'discussion_id' => 1, 'number' => 2, 'created_at' => Carbon::now(), 'user_id' => 1, 'type' => 'comment', 'content' => '<t><p>Mine</p></t>']);
        $count('discussion.created'); // warm up: first-request boot queries

        $one = ['discussion.created' => $count('discussion.created'), 'post.created' => $count('post.created')];

        // Two more discussions and replies, each by a different author.
        $now = Carbon::now();
        $this->database()->table('users')->insert([
            ['id' => 3, 'username' => 'third', 'email' => 'third@machine.local', 'is_email_confirmed' => 1, 'password' => 'x', 'joined_at' => $now],
            ['id' => 4, 'username' => 'fourth', 'email' => 'fourth@machine.local', 'is_email_confirmed' => 1, 'password' => 'x', 'joined_at' => $now],
        ]);
        foreach ([3, 4] as $user) {
            $this->database()->table('discussions')->insert(['id' => $user + 1, 'title' => "By $user", 'slug' => "by-$user", 'created_at' => $now, 'user_id' => $user, 'comment_count' => 2]);
            $this->database()->table('posts')->insert([
                ['id' => $user + 1, 'discussion_id' => $user + 1, 'number' => 1, 'created_at' => $now, 'user_id' => $user, 'type' => 'comment', 'content' => '<t><p>Hi</p></t>'],
                ['id' => $user + 10, 'discussion_id' => $user + 1, 'number' => 2, 'created_at' => $now, 'user_id' => $user, 'type' => 'comment', 'content' => '<t><p>Reply</p></t>'],
            ]);
            $this->database()->table('discussions')->where('id', $user + 1)->update(['first_post_id' => $user + 1]);
            $this->database()->table('discussion_tag')->insert(['discussion_id' => $user + 1, 'tag_id' => 2]);
        }

        $this->assertSame($one, ['discussion.created' => $count('discussion.created'), 'post.created' => $count('post.created')]);
    }

    #[Test]
    public function a_subscribed_hook_gets_a_signed_delivery_and_a_410_removes_it()
    {
        $this->prepareDatabase(['connect_hooks' => [
            ['id' => 1, 'api_key_id' => 3, 'event' => 'post.created', 'target_url' => 'https://93.184.216.34/zap', 'failures' => 0],
        ]]);

        $sent = [];
        $stack = HandlerStack::create(new MockHandler([new Response(200), new Response(410)]));
        $stack->push(Middleware::history($sent));
        $this->app()->getContainer()->instance(Http::class, new Http(['handler' => $stack]));

        [$status, $body] = $this->withKey('POST', '/api/connect/actions/posts', self::MEMBER_KEY, ['discussionId' => 1, 'content' => 'First']);
        $this->assertSame(201, $status, json_encode($body));

        $this->assertCount(1, $sent);
        $request = $sent[0]['request'];
        $this->assertSame('post.created', $request->getHeaderLine('X-Connect-Event'));
        $this->assertSame('sha256='.hash_hmac('sha256', (string) $request->getBody(), 'cs_r'), $request->getHeaderLine('X-Connect-Signature'));
        $this->assertSame(1, $this->database()->table('connect_hooks')->count());

        // The admin's reply, so flood control doesn't hold the member's second one.
        [$status] = $this->withKey('POST', '/api/connect/actions/posts', self::ADMIN_KEY, ['discussionId' => 1, 'content' => 'Second']);
        $this->assertSame(201, $status);
        $this->assertCount(2, $sent);
        $this->assertSame(0, $this->database()->table('connect_hooks')->count(), 'A 410 removes the subscription');
    }

    #[Test]
    public function the_api_strings_are_translated()
    {
        $locale = \Symfony\Component\Yaml\Yaml::parseFile(__DIR__.'/../../../locale/en.yml');

        foreach (['untitled_rule', 'invalid_pattern'] as $key) {
            $this->assertNotEmpty($locale['ernestdefoe-connect']['api'][$key] ?? null, $key);
        }
    }
}
