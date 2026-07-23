<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Model\Rule;
use Ernestdefoe\Connect\Rules\ActionRegistry;
use Ernestdefoe\Connect\Rules\Conditions;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/connect/rules  (create) · PATCH /api/connect/rules/{id} (update)
 *
 * Sanitises the rule: only a known trigger event, known operators, and known
 * action types survive. Bad actions/conditions are dropped rather than stored.
 */
class SaveRuleController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertAdmin();

        $id    = (int) Arr::get($request->getAttribute('routeParameters') ?? [], 'id', 0);
        $attrs = (array) Arr::get($request->getParsedBody() ?? [], 'data', []);

        $rule = $id ? Rule::query()->find($id) : new Rule();
        if ($id && ! $rule) {
            return new JsonResponse(['errors' => [['status' => '404', 'code' => 'not_found']]], 404);
        }

        if (array_key_exists('name', $attrs)) {
            $rule->name = mb_substr(trim((string) $attrs['name']), 0, 150) ?: 'Untitled rule';
        } elseif (! $rule->exists) {
            $rule->name = 'Untitled rule';
        }

        if (array_key_exists('event', $attrs)) {
            if (! EventRegistry::exists((string) $attrs['event'])) {
                return new JsonResponse(['errors' => [['status' => '422', 'code' => 'unknown_event']]], 422);
            }
            $rule->event = (string) $attrs['event'];
        } elseif (! $rule->exists) {
            return new JsonResponse(['errors' => [['status' => '422', 'code' => 'event_required']]], 422);
        }

        if (array_key_exists('enabled', $attrs)) {
            $rule->enabled = (bool) $attrs['enabled'];
        }
        if (array_key_exists('match', $attrs)) {
            $rule->match = in_array($attrs['match'], ['all', 'any'], true) ? $attrs['match'] : 'all';
        }
        if (array_key_exists('conditions', $attrs)) {
            $rule->conditions = $this->cleanConditions((array) $attrs['conditions']);
        }
        if (array_key_exists('actions', $attrs)) {
            $rule->actions = $this->cleanActions((array) $attrs['actions']);
        }
        if (array_key_exists('runAsUserId', $attrs)) {
            $rule->run_as_user_id = (int) $attrs['runAsUserId'] ?: null;
        } elseif (! $rule->exists) {
            $rule->run_as_user_id = (int) $actor->id;
        }

        $rule->save();

        return new JsonResponse(['data' => ListRulesController::present($rule)], $id ? 200 : 201);
    }

    private function cleanConditions(array $in): array
    {
        $out = [];
        foreach ($in as $c) {
            $c = (array) $c;
            $field = trim((string) ($c['field'] ?? ''));
            $op    = (string) ($c['op'] ?? '');
            if ($field !== '' && in_array($op, Conditions::OPERATORS, true)) {
                $out[] = ['field' => $field, 'op' => $op, 'value' => (string) ($c['value'] ?? '')];
            }
        }

        return $out;
    }

    private function cleanActions(array $in): array
    {
        $out = [];
        foreach ($in as $a) {
            $a = (array) $a;
            $type = (string) ($a['type'] ?? '');
            if (! ActionRegistry::exists($type)) {
                continue;
            }
            $clean = ['type' => $type];
            foreach (ActionRegistry::ACTIONS[$type][1] as $param) {
                $clean[$param] = (string) ($a[$param] ?? '');
            }
            $out[] = $clean;
        }

        return $out;
    }
}
