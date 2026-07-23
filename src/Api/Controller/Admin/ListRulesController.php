<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Model\Rule;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/rules — every automation rule, for the admin builder.
 */
class ListRulesController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        $rules = Rule::query()->orderBy('position')->orderBy('id')->get()
            ->map(fn (Rule $r) => self::present($r))->values()->all();

        return new JsonResponse(['data' => $rules]);
    }

    public static function present(Rule $r): array
    {
        return [
            'id'         => (int) $r->id,
            'name'       => $r->name,
            'event'      => $r->event,
            'enabled'    => (bool) $r->enabled,
            'match'      => $r->match,
            'conditions' => $r->conditions ?: [],
            'actions'    => $r->actions ?: [],
            'runAsUserId' => (int) $r->run_as_user_id,
            'runs'       => (int) $r->runs,
            'lastRunAt'  => optional($r->last_run_at)->toIso8601String(),
        ];
    }
}
