<?php

namespace Ernestdefoe\Connect\Console;

use Ernestdefoe\Connect\Model\ApiKey;
use Flarum\User\User;
use Illuminate\Console\Command;

/**
 * connect:key {label} {--user=} {--scopes=read,write}
 *
 * Mints a Connect API key from the CLI. Handy for setting up an integration
 * before the admin UI exists; prints the token + secret once (they're the only
 * time the raw values are shown outside the DB).
 */
class CreateKeyCommand extends Command
{
    protected $signature = 'connect:key {label : A name for the key} {--user= : User ID the key acts as (default: first admin)} {--scopes=read,write}';
    protected $description = 'Create a Connect API key';

    public function handle(): int
    {
        $userId = $this->option('user')
            ? (int) $this->option('user')
            : (int) (User::query()->where('is_email_confirmed', true)->orderBy('id')->value('id'));

        if (! $userId || ! User::query()->find($userId)) {
            $this->error('No valid user to attach the key to. Pass --user=<id>.');
            return 1;
        }

        $scopes = array_values(array_filter(array_map('trim', explode(',', (string) $this->option('scopes')))));

        $key = ApiKey::build((string) $this->argument('label'), $userId, $scopes);
        $key->save();

        $this->info('Connect API key created.');
        $this->line('  Token  (Bearer): ' . $key->token);
        $this->line('  Secret (HMAC):   ' . $key->secret);
        $this->line('  Acts as user:    ' . $userId);
        $this->line('  Scopes:          ' . implode(', ', $scopes ?: ['*']));

        return 0;
    }
}
