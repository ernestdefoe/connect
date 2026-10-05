<?php

namespace Ernestdefoe\Connect\Middleware;

use Carbon\Carbon;
use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Webhook\Audience;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Recognises `Authorization: Bearer ck_…` on Connect's integration routes and
 * hands the key to the controller, which acts as the key's user.
 *
 * A key is only honoured on the routes below, and only with the scope each one
 * needs. Anywhere else in the API the header is ignored and the request is
 * handled exactly as if it were not there (no actor, no CSRF bypass), so a key
 * can never reach core routes or Connect's admin routes. Keys of a suspended
 * user are refused until the suspension ends.
 */
class AuthenticateWithConnectKey implements MiddlewareInterface
{
    /** route name => scope the key needs (null = any valid key) */
    public const ROUTES = [
        'connect.me'                  => null,
        'connect.hooks.subscribe'     => 'read',
        'connect.hooks.unsubscribe'   => null,
        'connect.samples'             => 'read',
        'connect.discussions'         => 'read',
        'connect.tags'                => 'read',
        'connect.actions.discussions' => 'write',
        'connect.actions.posts'       => 'write',
    ];

    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->bearer($request);
        $route = (string) $request->getAttribute('routeName');

        if (! $token || ! str_starts_with($token, 'ck_') || ! array_key_exists($route, self::ROUTES)) {
            return $handler->handle($request);
        }

        $key = ApiKey::query()->where('token', $token)->with('user')->first();

        if (! $key || ! $key->user) {
            return $handler->handle($request); // the controller answers 401
        }

        if (Audience::suspended($key->user)) {
            return $this->refuse('user_suspended');
        }

        $scope = self::ROUTES[$route];
        if ($scope !== null && ! $key->hasScope($scope)) {
            return $this->refuse('insufficient_scope');
        }

        ApiKey::query()->whereKey($key->id)->update(['last_used_at' => Carbon::now()]);

        return $handler->handle($request
            ->withAttribute('connectApiKey', $key)
            // Token-authenticated calls carry no session CSRF token; this is why
            // the middleware runs before CheckCsrfToken (see extend.php).
            ->withAttribute('bypassCsrfToken', true));
    }

    private function refuse(string $code): ResponseInterface
    {
        return new JsonResponse(['errors' => [['status' => '403', 'code' => $code]]], 403);
    }

    private function bearer(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
    }
}
