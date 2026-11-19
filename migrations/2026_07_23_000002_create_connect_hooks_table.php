<?php

use Flarum\Database\Migration;
use Illuminate\Database\Schema\Blueprint;

/**
 * Webhook subscriptions (REST Hooks). One row per active Zap/scenario: an event
 * name + the target URL Zapier/Make/etc. gave us on subscribe. When the event
 * fires we POST a signed payload to every matching target_url. A 410 from the
 * target means the subscription is gone, and the row is pruned automatically.
 */
return Migration::createTableIfNotExists('connect_hooks', function (Blueprint $table) {
    $table->increments('id');
    $table->unsignedInteger('api_key_id');
    $table->string('event', 60);                // e.g. discussion.created
    $table->string('target_url', 2048);
    $table->string('zap_id', 60)->nullable();   // bundle.meta.zap.id, for a "connected Zaps" view
    $table->unsignedInteger('failures')->default(0);
    $table->timestamps();

    $table->foreign('api_key_id')->references('id')->on('connect_api_keys')->cascadeOnDelete();
    $table->index(['event']);
});
