<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Scoped API keys. Each key belongs to a user (the actor its actions run as) and
 * carries a set of scopes. External services (Zapier, Make, IFTTT, n8n) present
 * the key as a Bearer token; outgoing webhooks are HMAC-signed with its secret.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('connect_api_keys')) {
            return;
        }

        $schema->create('connect_api_keys', function (Blueprint $table) {
            $table->increments('id');
            $table->string('label', 100);
            $table->string('token', 80)->unique();     // the Bearer value (public id + secret)
            $table->string('secret', 80);               // HMAC signing secret for outbound hooks
            $table->unsignedInteger('user_id')->nullable();
            $table->text('scopes')->nullable();         // JSON array; null/[] = all
            $table->dateTime('last_used_at')->nullable();
            $table->timestamps();

            $table->foreign('user_id')->references('id')->on('users')->nullOnDelete();
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('connect_api_keys');
    },
];
