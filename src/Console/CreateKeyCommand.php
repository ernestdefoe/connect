<?php

namespace Ernestdefoe\Connect\Console;

use Ernestdefoe\Connect\Model\ApiKey;
use Ernestdefoe\Connect\Support\Admins;
use Flarum\User\User;
use Illuminate\Console\Command;

/**
 * connect:key {label} {--user=} {--scopes=read,write}.
 *
 * Mints a Connect API key from the CLI. Handy for scripting an integration
 * setup; prints the token + secret once (the token is
 * never shown again: only its hash is stored).
 */
class CreateKeyCommand extends Command
{
    protected $signature = 'connect:key {label : A name for the key} {--user= : User ID the key acts as (default: first admin)} {--scopes=read,write}';
    protected $description = 'Create a Connect API key';

    public function handle(): int
    {
        $userId = $this->option('user')
            ? (int) $this->option('user')
            : (int) Admins::first()?->id;

        if (! $userId || ! User::query()->find($userId)) {
            $this->error('No valid user to attach the key to. Pass --user=<id>.');

            return 1;
        }

        $scopes = array_values(array_intersect(array_map('trim', explode(',', (string) $this->option('scopes'))), ApiKey::SCOPES));

        $key = ApiKey::build((string) $this->argument('label'), $userId, $scopes);
        $key->save();

        $this->info('Connect API key created.');
        $this->line('  Token  (Bearer): '.$key->plainToken);
        $this->line('  Secret (HMAC):   '.$key->secret);
        $this->line('  Acts as user:    '.$userId);
        $this->line('  Scopes:          '.implode(', ', $scopes ?: ['*']));

        return 0;
    }
}
