<?php

namespace Ernestdefoe\Connect\Webhook;

use Ernestdefoe\Connect\Model\Hook;
use GuzzleHttp\Client;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Support\Str;

/**
 * Delivers one signed webhook. Queued, so it runs off the request path (or
 * inline on the sync driver). The body is HMAC-signed with the key's secret so
 * receivers can verify authenticity. A 410 means the subscriber (e.g. a turned-
 * off Zap) is gone, so we delete the hook and stop delivering to it.
 */
class SendWebhook implements ShouldQueue
{
    use Queueable;
    use InteractsWithQueue;

    public int $tries = 3;
    public int $backoff = 10;

    public function __construct(
        protected int $hookId,
        protected string $targetUrl,
        protected string $secret,
        protected string $event,
        protected array $payload
    ) {
    }

    public function handle(): void
    {
        $body = json_encode([
            'event'   => $this->event,
            'data'    => $this->payload,
        ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);

        $signature = hash_hmac('sha256', $body, $this->secret);

        try {
            (new Client())->post($this->targetUrl, [
                'body'    => $body,
                'headers' => [
                    'Content-Type'        => 'application/json',
                    'User-Agent'          => 'Flarum-Connect/1.0',
                    'X-Connect-Event'     => $this->event,
                    'X-Connect-Delivery'  => (string) Str::uuid(),
                    'X-Connect-Signature' => 'sha256=' . $signature,
                ],
                'timeout'         => 15,
                'connect_timeout' => 8,
                'http_errors'     => true,
            ]);
        } catch (\GuzzleHttp\Exception\RequestException $e) {
            $status = $e->getResponse()?->getStatusCode();

            // The subscriber is gone (Zap turned off) — prune and stop.
            if ($status === 410) {
                Hook::query()->whereKey($this->hookId)->delete();
                return;
            }

            throw $e; // let the queue retry other failures
        }
    }
}
