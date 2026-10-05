<?php

namespace Ernestdefoe\Connect\Rules;

use Ernestdefoe\Connect\Model\Rule;
use Illuminate\Contracts\Bus\Dispatcher as Bus;

/**
 * On each fired trigger, finds the enabled rules for that event whose conditions
 * pass, and queues each to run. Condition matching is cheap and done up front so
 * only genuinely-matching rules get enqueued.
 */
class Engine
{
    public function __construct(
        protected Bus $bus
    ) {
    }

    public function fire(string $event, array $payload): void
    {
        // Something a rule just did: see ActionRunner::$running.
        if (ActionRunner::$running > 0) {
            return;
        }

        Rule::query()->where('event', $event)->where('enabled', true)->orderBy('position')->get()
            ->each(function (Rule $rule) use ($event, $payload) {
                if (Conditions::pass($rule->conditions ?? [], $rule->match, $payload)) {
                    $this->bus->dispatch(new RunRule($rule->id, $event, $payload));
                }
            });
    }
}
