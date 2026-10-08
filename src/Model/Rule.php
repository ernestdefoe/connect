<?php

namespace Ernestdefoe\Connect\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * @property int $id
 * @property string $name
 * @property string $event
 * @property bool $enabled
 * @property string $match
 * @property ?array $conditions
 * @property ?array $actions
 * @property ?int $run_as_user_id
 * @property int $runs
 * @property ?\Carbon\Carbon $last_run_at
 * @property ?\Carbon\Carbon $created_at
 * @property ?\Carbon\Carbon $updated_at
 * @property-read ?User $runAsUser
 */
class Rule extends AbstractModel
{
    protected $table = 'connect_rules';
    protected $guarded = [];
    protected $casts = [
        'enabled'     => 'boolean',
        'conditions'  => 'array',
        'actions'     => 'array',
        'last_run_at' => 'datetime',
    ];

    /** @return BelongsTo<User, $this> */
    public function runAsUser(): BelongsTo
    {
        return $this->belongsTo(User::class, 'run_as_user_id');
    }
}
