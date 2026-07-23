<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * In-app automation rules (the "Rules" engine). Each rule is: a trigger event,
 * a set of conditions (filters), and a set of actions to run when a matching
 * event fires and the conditions pass — no external service required.
 */
return [
    'up' => function (Builder $schema) {
        if ($schema->hasTable('connect_rules')) {
            return;
        }

        $schema->create('connect_rules', function (Blueprint $table) {
            $table->increments('id');
            $table->string('name', 150);
            $table->string('event', 60);                // trigger, e.g. discussion.created
            $table->boolean('enabled')->default(true);
            $table->string('match', 4)->default('all'); // all | any (of the conditions)
            $table->text('conditions')->nullable();     // JSON: [{field, op, value}]
            $table->text('actions')->nullable();        // JSON: [{type, ...params}]
            $table->unsignedInteger('run_as_user_id')->nullable(); // actor for actions
            $table->unsignedInteger('runs')->default(0);
            $table->dateTime('last_run_at')->nullable();
            $table->integer('position')->default(0);
            $table->timestamps();

            $table->foreign('run_as_user_id')->references('id')->on('users')->nullOnDelete();
            $table->index(['event', 'enabled']);
        });
    },

    'down' => function (Builder $schema) {
        $schema->dropIfExists('connect_rules');
    },
];
