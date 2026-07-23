<?php

namespace Ernestdefoe\Connect\Api\Controller\Admin;

use Ernestdefoe\Connect\Webhook\EventRegistry;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/connect/events — the trigger catalog, so the admin page can show
 * which events are available to automate on.
 */
class ListEventsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        RequestUtil::getActor($request)->assertAdmin();

        return new JsonResponse(['data' => EventRegistry::all()]);
    }
}
