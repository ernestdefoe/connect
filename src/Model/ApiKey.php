<?php

namespace Ernestdefoe\Connect\Model;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Carbon\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property string $label
 * @property string $token SHA-256 of the raw Bearer key
 * @property ?string $token_prefix
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

    /** Characters of the raw key kept in clear, so an admin can tell keys apart. */
    public const PREFIX_LENGTH = 10;

    protected $fillable = ['label', 'user_id', 'scopes'];

    /** The raw Bearer key, set only on the request that minted it: only its hash is stored. */
    public ?string $plainToken = null;
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

    /**
     * Give the key a fresh Bearer token and HMAC signing secret. The token is
     * stored as a SHA-256 hash (it is 48 random characters, so a fast hash is
     * enough), the raw value is kept on the model for this request only. The
     * secret stays readable: Connect needs it to sign every webhook.
     */
    public function mint(): static
    {
        $this->plainToken = 'ck_'.Str::random(48);
        $this->token = self::hash($this->plainToken);
        $this->token_prefix = substr($this->plainToken, 0, self::PREFIX_LENGTH);
        $this->secret = 'cs_'.Str::random(48);

        return $this;
    }

    public static function hash(string $token): string
    {
        return hash('sha256', $token);
    }

    /** The key a raw Bearer token belongs to, if any. */
    public static function findByToken(string $token): ?self
    {
        return self::query()->where('token', self::hash($token))->with('user')->first();
    }

    /** Record a use, at most once a minute, so a busy Zap isn't an UPDATE per call. */
    public function touchLastUsed(): void
    {
        if ($this->last_used_at === null || $this->last_used_at->lt(Carbon::now()->subMinute())) {
            self::query()->whereKey($this->id)->update(['last_used_at' => Carbon::now()]);
        }
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
