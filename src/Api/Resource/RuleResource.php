<?php

namespace Ernestdefoe\Connect\Api\Resource;

use Ernestdefoe\Connect\Model\Rule;
use Ernestdefoe\Connect\Rules\ActionRegistry;
use Ernestdefoe\Connect\Rules\Conditions;
use Ernestdefoe\Connect\Webhook\EventRegistry;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Flarum\Locale\TranslatorInterface;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;

/**
 * Admin-only automation rules (/api/connect-rules). Only a known trigger event,
 * known operators and known action types are stored: unknown conditions and
 * actions are dropped, and a "matches" value that doesn't compile is refused.
 *
 * @extends AbstractDatabaseResource<Rule>
 */
class RuleResource extends AbstractDatabaseResource
{
    public function __construct(
        protected TranslatorInterface $translator
    ) {
    }

    public function type(): string
    {
        return 'connect-rules';
    }

    public function model(): string
    {
        return Rule::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->orderBy('position')->orderBy('id');
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->admin(),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Update::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name')->writable()->nullable()->maxLength(150)
                ->set(fn (Rule $rule, ?string $value) => $rule->name = trim((string) $value) ?: $this->untitled()),
            Schema\Str::make('event')->writable()->requiredOnCreate()->in(array_keys(EventRegistry::EVENTS)),
            Schema\Boolean::make('enabled')->writable(),
            Schema\Str::make('match')->writable()->in(['all', 'any']),
            Schema\Arr::make('conditions')->writable()
                ->rule(fn () => $this->validPatterns(...))
                ->get(fn (Rule $rule) => $rule->conditions ?: [])
                ->set(fn (Rule $rule, array $value) => $rule->conditions = Conditions::clean($value)),
            Schema\Arr::make('actions')->writable()
                ->get(fn (Rule $rule) => $rule->actions ?: [])
                ->set(fn (Rule $rule, array $value) => $rule->actions = ActionRegistry::clean($value)),
            Schema\Integer::make('runAsUserId')->property('run_as_user_id')->writable()->nullable()->rule('exists:users,id'),
            Schema\Integer::make('runs'),
            Schema\DateTime::make('lastRunAt')->property('last_run_at'),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        $model->name ??= $this->untitled();
        $model->run_as_user_id ??= $context->getActor()->id;

        return $model;
    }

    /** Refuse a "matches" value that doesn't compile, so a typo isn't a rule that silently never matches. */
    private function validPatterns(string $attribute, mixed $value, \Closure $fail): void
    {
        foreach ((array) $value as $condition) {
            $condition = (array) $condition;
            if (($condition['op'] ?? '') === 'matches' && ! Conditions::validPattern((string) ($condition['value'] ?? ''))) {
                $fail($this->translator->trans('ernestdefoe-connect.api.invalid_pattern'));

                return;
            }
        }
    }

    private function untitled(): string
    {
        return $this->translator->trans('ernestdefoe-connect.api.untitled_rule');
    }
}
