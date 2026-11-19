<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Rules\ActionRegistry;
use Ernestdefoe\Connect\Rules\Conditions;
use Ernestdefoe\Connect\Support\Tags;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Flarum\Group\Group;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/meta — everything the Rules builder needs to render its
 * dropdowns: triggers, actions, operators, and the groups/tags an action can
 * target on this install.
 */
class MetaController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $groups = Group::query()->whereNotIn('id', [Group::GUEST_ID, Group::MEMBER_ID])
            ->get(['id', 'name_singular'])
            ->map(fn ($g) => ['id' => (int) $g->id, 'name' => $g->name_singular])->values()->all();

        $tags = [];
        if (Tags::enabled()) {
            $tags = \Flarum\Tags\Tag::query()->get(['id', 'name'])
                ->map(fn ($t) => ['id' => (int) $t->id, 'name' => $t->name])->values()->all();
        }

        return new JsonResponse(['data' => [
            'events' => EventRegistry::all(),
            'actions' => ActionRegistry::available(),
            'operators' => Conditions::OPERATORS,
            'groups' => $groups,
            'tags' => $tags,
        ]]);
    }
}
