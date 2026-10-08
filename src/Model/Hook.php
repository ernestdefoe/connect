<?php

namespace Ernestdefoe\Connect\Model;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property int $api_key_id
 * @property string $event
 * @property string $target_url
 * @property ?string $zap_id
 * @property int $failures
 * @property ?\Carbon\Carbon $created_at
 * @property ?\Carbon\Carbon $updated_at
 * @property-read ?ApiKey $apiKey
 */
class Hook extends AbstractModel
{
    protected $table = 'connect_hooks';
    protected $guarded = [];

    /** @return BelongsTo<ApiKey, $this> */
    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }
}
