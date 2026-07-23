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
 */
class Hook extends AbstractModel
{
    protected $table = 'connect_hooks';
    protected $guarded = [];

    public function apiKey(): BelongsTo
    {
        return $this->belongsTo(ApiKey::class, 'api_key_id');
    }
}
