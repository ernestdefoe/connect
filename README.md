# Connect for Flarum 2

> Automation for Flarum 2 — outgoing webhooks, a scoped REST API, and (soon) an
> in-app if-this-then-that Rules engine. Works with **Zapier, Make, IFTTT, n8n**
> and anything that speaks webhooks. Free and MIT-licensed.

## Status

Early development. The webhook + REST core is in place:

- **Scoped API keys** — Bearer tokens that act as a chosen user, with `read` /
  `write` scopes. Mint one with `php flarum connect:key "My key"`.
- **REST Hooks** — external services subscribe (`POST /api/connect/hooks`) and
  unsubscribe (`DELETE /api/connect/hooks/{id}`). A `410` from a target auto-
  prunes the subscription (e.g. a turned-off Zap).
- **Triggers** — `discussion.created`, `post.created`, `user.registered`, fired
  from Flarum's own events. Payloads are HMAC-signed (`X-Connect-Signature`).
- **Sample data** — `GET /api/connect/samples/{event}` returns recent real items
  shaped like the webhook payload, for Zapier's Zap-setup step.
- **Actions** — `POST /api/connect/actions/discussions` creates a discussion as
  the key's user through Flarum's internal API (full permissions + validation).
- **Auth test** — `GET /api/connect/me`.

## Roadmap

- Admin panel: create/revoke keys, see connected subscriptions, delivery log.
- More triggers (tags, groups, flags, badges) and actions (reply, DM, tag, group).
- In-app **Rules** engine (trigger → filters → actions), no external service.
- A published **Zapier app** on top of this API + an embedded connect UI.

## Webhook verification

Each delivery is signed. Verify with the key's secret:

```
signature == 'sha256=' + hmac_sha256(rawRequestBody, keySecret)
```

## License

MIT.
