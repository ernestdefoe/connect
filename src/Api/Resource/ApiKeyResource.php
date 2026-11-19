<?php

namespace Ernestdefoe\Connect\Api\Resource;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;

/**
 * Admin-only Connect API keys (/api/connect-keys). The token and secret are
 * included because the admin pastes them into Zapier, Make and the like. A key
 * acts as its user; it defaults to the admin who created it.
 *
 * @extends AbstractDatabaseResource<ApiKey>
 */
class ApiKeyResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'connect-keys';
    }

    public function model(): string
    {
        return ApiKey::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->withCount('hooks')->latest();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->admin()->eagerLoad('user'),
            Endpoint\Create::make()->authenticated()->admin(),
            Endpoint\Delete::make()->authenticated()->admin(),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('label')->writableOnCreate()->requiredOnCreate()->maxLength(100),
            Schema\Str::make('token'),
            Schema\Str::make('secret'),
            Schema\Arr::make('scopes')
                ->writableOnCreate()
                ->nullable()
                ->get(fn (ApiKey $key) => $key->scopes ?: ['*'])
                ->set(fn (ApiKey $key, ?array $value) => $key->scopes = array_values(array_intersect($value ?? [], ApiKey::SCOPES))),
            Schema\Integer::make('userId')->property('user_id')->writableOnCreate()->nullable()->rule('exists:users,id'),
            Schema\Str::make('user')->get(fn (ApiKey $key) => $key->user?->username),
            Schema\Integer::make('hooks')->get(fn (ApiKey $key) => (int) $key->hooks_count),
            Schema\DateTime::make('lastUsedAt')->property('last_used_at'),
            Schema\DateTime::make('createdAt')->property('created_at'),
        ];
    }

    public function creating(object $model, Context $context): ?object
    {
        $model->user_id ??= $context->getActor()->id;

        return $model->mint();
    }

    /** Revoking a key stops every subscription made with it (SQLite doesn't cascade the foreign key). */
    public function deleting(object $model, Context $context): void
    {
        $model->hooks()->delete();
    }
}
