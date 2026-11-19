<?php

namespace Ernestdefoe\Connect\Api\Resource;

use Ernestdefoe\Connect\Model\Hook;
use Flarum\Api\Endpoint;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context;

/**
 * Admin view of every live webhook subscription (/api/connect-subscriptions):
 * which service listens for which event, through which key. Subscriptions are
 * made and removed by the services themselves (REST Hooks), so this is read-only.
 *
 * @extends AbstractDatabaseResource<Hook>
 */
class HookResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'connect-subscriptions';
    }

    public function model(): string
    {
        return Hook::class;
    }

    public function scope(Builder $query, Context $context): void
    {
        $query->latest();
    }

    public function endpoints(): array
    {
        return [
            Endpoint\Index::make()->admin()->eagerLoad('apiKey'),
        ];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('event'),
            Schema\Str::make('targetUrl')->property('target_url'),
            Schema\Str::make('zapId')->property('zap_id')->nullable(),
            Schema\Integer::make('keyId')->property('api_key_id'),
            Schema\Str::make('keyLabel')->get(fn (Hook $hook) => $hook->apiKey?->label),
            Schema\DateTime::make('createdAt')->property('created_at'),
        ];
    }
}
