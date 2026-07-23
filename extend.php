<?php

/*
 * This file is part of ernestdefoe/connect.
 *
 * Licensed under the MIT license.
 */

namespace Ernestdefoe\Connect;

use Ernestdefoe\Connect\Api\Controller;
use Ernestdefoe\Connect\Console\CreateKeyCommand;
use Ernestdefoe\Connect\Listener\DispatchWebhooks;
use Ernestdefoe\Connect\Middleware\AuthenticateWithConnectKey;
use Flarum\Extend;
use Flarum\Http\Middleware\CheckCsrfToken;

return [
    new Extend\Locales(__DIR__ . '/locale'),

    (new Extend\Console())
        ->command(CreateKeyCommand::class),

    // Recognise Connect Bearer keys on the API and resolve them to an actor.
    // Must run before the CSRF check so token-auth POSTs aren't rejected, and
    // before the actor is populated so our resolved user is the one used.
    (new Extend\Middleware('api'))
        ->insertBefore(CheckCsrfToken::class, AuthenticateWithConnectKey::class),

    // Fire subscribed webhooks from Flarum's domain events.
    (new Extend\Event())
        ->subscribe(DispatchWebhooks::class),

    (new Extend\Routes('api'))
        // Connection test + auth (Zapier auth.test)
        ->get('/connect/me', 'connect.me', Controller\MeController::class)
        // REST Hooks: subscribe / unsubscribe
        ->post('/connect/hooks', 'connect.hooks.subscribe', Controller\SubscribeHookController::class)
        ->delete('/connect/hooks/{id}', 'connect.hooks.unsubscribe', Controller\UnsubscribeHookController::class)
        // performList sample data for the Zap-setup step
        ->get('/connect/samples/{event}', 'connect.samples', Controller\SampleController::class)
        // Actions (Creates)
        ->post('/connect/actions/discussions', 'connect.actions.discussions', Controller\Action\CreateDiscussionController::class),
];
