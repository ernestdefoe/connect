<?php

namespace Ernestdefoe\Connect\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $label
 * @property string $token
 * @property string $secret
 * @property ?int $user_id
 * @property ?array $scopes
 * @property ?\Carbon\Carbon $last_used_at
 * @property ?\Carbon\Carbon $created_at
 * @property ?\Carbon\Carbon $updated_at
 * @property-read ?User $user
 * @property-read \Illuminate\Database\Eloquent\Collection<int, Hook> $hooks
 */
class ApiKey extends AbstractModel
{
    protected $table = 'connect_api_keys';
    /** The scopes a key can carry; none means all of them. */
    public const SCOPES = ['read', 'write'];

    protected $fillable = ['label', 'user_id', 'scopes'];
    protected $casts = [
        'scopes' => 'array',
        'last_used_at' => 'datetime',
    ];

    /** Mint a new key for a user, returning the unsaved model (caller saves). */
    public static function build(string $label, ?int $userId, array $scopes = []): self
    {
        $key = new self();
        $key->label = mb_substr($label, 0, 100) ?: 'API key';
        $key->user_id = $userId;
        $key->scopes = $scopes;

        return $key->mint();
    }

    /** Give the key a fresh Bearer token and HMAC signing secret. */
    public function mint(): static
    {
        $this->token = 'ck_'.Str::random(48);
        $this->secret = 'cs_'.Str::random(48);

        return $this;
    }

    public function hasScope(string $scope): bool
    {
        $scopes = $this->scopes ?? [];

        return empty($scopes) || in_array($scope, $scopes, true) || in_array('*', $scopes, true);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return HasMany<Hook, $this> */
    public function hooks(): HasMany
    {
        return $this->hasMany(Hook::class, 'api_key_id');
    }
}
