<?php

namespace Ernestdefoe\Connect\Rules;

use Carbon\Carbon;
use Ernestdefoe\Connect\Model\Rule;
use Flarum\User\User;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Runs one rule against one fired event, off the request path. Re-checks the
 * rule is still enabled and its conditions still pass (state can change between
 * enqueue and run), resolves the actor, executes the actions, and bumps counters.
 */
class RunRule implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public int $tries = 2;

    public function __construct(
        protected int $ruleId,
        protected string $event,
        protected array $payload
    ) {
    }

    public function handle(ActionRunner $runner): void
    {
        /** @var Rule|null $rule */
        $rule = Rule::query()->find($this->ruleId);
        if (! $rule || ! $rule->enabled || $rule->event !== $this->event) {
            return;
        }
        if (! Conditions::pass($rule->conditions ?? [], $rule->match, $this->payload)) {
            return;
        }

        $actor = $this->actor($rule->run_as_user_id);
        if (! $actor) {
            return;
        }

        $runner->run($rule->actions ?? [], $this->event, $this->payload, $actor);

        $rule->forceFill(['runs' => $rule->runs + 1, 'last_run_at' => Carbon::now()])->saveQuietly();
    }

    private function actor(?int $userId): ?User
    {
        if ($userId && ($u = User::query()->find($userId))) {
            return $u;
        }

        // Fall back to the first admin so a rule still runs if its actor is gone.
        return User::query()
            ->whereExists(fn ($q) => $q->selectRaw(1)->from('group_user')
                ->whereColumn('group_user.user_id', 'users.id')->where('group_id', 1))
            ->orderBy('id')->first();
    }
}
