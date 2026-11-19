<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Keys used to be stored as the raw Bearer value. Replace each with its SHA-256
 * hash (what ApiKey::findByToken() looks up) and keep its first characters in a
 * new token_prefix column, so admins can still tell keys apart. Clients keep
 * sending the raw key, so existing connections still work.
 *
 * Only raw values are hashed: they start with "ck_", which a hex hash cannot,
 * so running this twice changes nothing.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasColumn('connect_api_keys', 'token_prefix')) {
            $schema->table('connect_api_keys', function (Blueprint $table) {
                $table->string('token_prefix', 16)->nullable();
            });
        }

        $db = $schema->getConnection();

        // SHA-256 has no portable SQL form, so each existing key gets one small
        // UPDATE, in chunks. This runs once per install, over a handful of keys.
        $db->table('connect_api_keys')->where('token', 'like', 'ck_%')->orderBy('id')
            ->chunkById(100, function ($keys) use ($db) {
                foreach ($keys as $key) {
                    $db->table('connect_api_keys')->where('id', $key->id)->update([
                        'token' => hash('sha256', $key->token),
                        'token_prefix' => substr($key->token, 0, 10),
                    ]);
                }
            });
    },

    // The prefix column goes; the hashes stay, as a hash can't be turned back
    // into the key (owners would need new keys under an older Connect).
    'down' => function (Builder $schema) {
        $schema->table('connect_api_keys', function (Blueprint $table) {
            $table->dropColumn('token_prefix');
        });
    },
];
