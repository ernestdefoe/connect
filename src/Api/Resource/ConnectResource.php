<?php

namespace Ernestdefoe\Connect\Api\Resource;

use Closure;
use Ernestdefoe\Connect\Integration\Actions;
use Ernestdefoe\Connect\Integration\Errors;
use Ernestdefoe\Connect\Integration\Hooks;
use Ernestdefoe\Connect\Integration\Lookups;
use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Rules\BuilderOptions;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Flarum\Api\Context;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractResource;
use Flarum\Settings\SettingsRepositoryInterface;
use Flarum\User\User;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;

/**
 * Connect's integration routes under /api/connect/…, for Zapier, Make, IFTTT,
 * n8n and REST Hooks. These services speak plain JSON, not JSON:API, so each
 * endpoint answers with its own response body; the route paths and names are
 * the contract the published Zapier app is built on.
 *
 * Every integration endpoint needs a Connect key: AuthenticateWithConnectKey
 * makes the key's user the actor on these routes (and checks the key's scope),
 * so ->authenticated() refuses everyone else.
 */
class ConnectResource extends AbstractResource
{
    public function __construct(
        protected Hooks $hooks,
        protected Lookups $lookups,
        protected Actions $actions,
        protected SettingsRepositoryInterface $settings
    ) {
    }

    public function type(): string
    {
        return 'connect';
    }

    public function endpoints(): array
    {
        return [
            // Connection test + label for Zapier's auth step
            $this->withKey('me', 'GET', '/me', fn (ApiKey $key, User $actor) => new JsonResponse(['data' => ['attributes' => [
                'forumTitle' => (string) $this->settings->get('forum_title'),
                'user' => $actor->username,
                'userId' => (int) $actor->id,
                'keyLabel' => $key->label,
            ]]])),
            $this->withKey('hooks.subscribe', 'POST', '/hooks', fn (ApiKey $key, User $actor, Context $context) => $this->hooks->subscribe($key, (array) $context->request->getParsedBody())),
            $this->withKey('hooks.unsubscribe', 'DELETE', '/hooks/{hook}', fn (ApiKey $key, User $actor, Context $context) => $this->hooks->unsubscribe($key, (int) $this->routeParameter($context, 'hook'))),
            $this->withKey('samples', 'GET', '/samples/{event}', function (ApiKey $key, User $actor, Context $context) {
                $event = (string) $this->routeParameter($context, 'event');

                return EventRegistry::exists($event)
                    ? new JsonResponse($this->lookups->samples($actor, $event))
                    : Errors::json(404, 'unknown_event');
            }),
            // Pickers behind Zapier's dynamic dropdowns
            $this->withKey('discussions', 'GET', '/discussions', fn (ApiKey $key, User $actor, Context $context) => new JsonResponse($this->lookups->discussions(
                $actor,
                (int) Arr::get($context->request->getQueryParams(), 'page', 0),
                (int) Arr::get($context->request->getQueryParams(), 'limit', 50)
            ))),
            $this->withKey('tags', 'GET', '/tags', fn (ApiKey $key, User $actor) => new JsonResponse($this->lookups->tags($actor))),
            // Actions (Zapier "creates")
            $this->withKey('actions.discussions', 'POST', '/actions/discussions', fn (ApiKey $key, User $actor, Context $context) => $this->actions->startDiscussion($actor, (array) $context->request->getParsedBody())),
            $this->withKey('actions.posts', 'POST', '/actions/posts', fn (ApiKey $key, User $actor, Context $context) => $this->actions->reply($actor, (array) $context->request->getParsedBody())),

            // What the admin Rules builder can offer on this forum
            Endpoint\Endpoint::make('meta')
                ->route('GET', '/meta')
                ->admin()
                ->action(fn () => new JsonResponse(['data' => BuilderOptions::all()]))
                ->response(fn (Context $context, ResponseInterface $response) => $response),
        ];
    }

    /**
     * An endpoint that only answers a request carrying a Connect key. A signed-in
     * user without one gets the same 401 as a guest.
     *
     * @param Closure(ApiKey, User, Context): ResponseInterface $handle
     */
    private function withKey(string $name, string $method, string $path, Closure $handle): Endpoint\Endpoint
    {
        return Endpoint\Endpoint::make($name)
            ->route($method, $path)
            ->authenticated()
            ->action(function (Context $context) use ($handle) {
                $key = $context->request->getAttribute('connectApiKey');

                return $key instanceof ApiKey
                    ? $handle($key, $context->getActor(), $context)
                    : Errors::json(401, 'not_authenticated');
            })
            ->response(fn (Context $context, ResponseInterface $response) => $response);
    }

    private function routeParameter(Context $context, string $name): ?string
    {
        return Arr::get($context->request->getAttribute('routeParameters') ?? [], $name);
    }
}
