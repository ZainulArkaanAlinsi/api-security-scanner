<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class ApiToken extends Model
{
    /** Prefix makes the string recognisable in logs and secret scanners. */
    public const PREFIX = 'apisc_';

    protected $fillable = ['name', 'token_hash', 'last_used_at'];

    protected $casts = ['last_used_at' => 'datetime'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Generate a token, store only its hash, and hand the plain value back once.
     *
     * @return array{token: string, model: self}
     */
    public static function issue(User $user, string $name): array
    {
        $plain = self::PREFIX.Str::random(48);

        $model = $user->apiTokens()->create([
            'name' => $name,
            'token_hash' => hash('sha256', $plain),
        ]);

        return ['token' => $plain, 'model' => $model];
    }

    public static function findByPlainToken(string $plain): ?self
    {
        return static::with('user')->firstWhere('token_hash', hash('sha256', $plain));
    }

    public function preview(): string
    {
        return self::PREFIX.'…'.substr($this->token_hash, -6);
    }
}
