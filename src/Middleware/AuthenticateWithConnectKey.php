<?php

namespace Ernestdefoe\Connect\Middleware;

use Carbon\Carbon;
use Ernestdefoe\Connect\Model\ApiKey;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\MiddlewareInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * Recognises `Authorization: Bearer ck_…` on Connect API routes, resolves the
 * key to its owning user, and sets that user as the request actor — so the
 * normal controllers + permission checks apply to whatever the external service
 * does. Requests without a Connect key pass straight through untouched (Flarum's
 * own auth still runs), so this never interferes with the rest of the API.
 */
class AuthenticateWithConnectKey implements MiddlewareInterface
{
    public function process(ServerRequestInterface $request, RequestHandlerInterface $handler): ResponseInterface
    {
        $token = $this->bearer($request);

        if ($token && str_starts_with($token, 'ck_')) {
            $key = ApiKey::query()->where('token', $token)->first();

            if ($key && $key->user) {
                ApiKey::query()->whereKey($key->id)->update(['last_used_at' => Carbon::now()]);

                $request = $request
                    ->withAttribute('actor', $key->user)
                    ->withAttribute('connectApiKey', $key)
                    // Token-authenticated API calls don't carry a session CSRF
                    // token; this bypass is why the middleware must run before
                    // CheckCsrfToken (see extend.php insertBefore).
                    ->withAttribute('bypassCsrfToken', true);
            }
        }

        return $handler->handle($request);
    }

    private function bearer(ServerRequestInterface $request): ?string
    {
        $header = $request->getHeaderLine('Authorization');

        return preg_match('/^Bearer\s+(\S+)$/i', $header, $m) ? $m[1] : null;
    }
}
