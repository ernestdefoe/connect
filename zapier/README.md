# Connect for Flarum — Zapier integration

The Zapier app that sits on top of the [Connect](../README.md) extension. It lets
anyone automate their **self-hosted** Flarum 2 forum from Zapier — no shared
OAuth app, because each customer authenticates against their own site.

## Triggers

| Trigger | Fires when | Backed by |
| --- | --- | --- |
| **New Discussion** | a discussion is started | `discussion.created` REST Hook |
| **New Reply** | a reply is posted | `post.created` REST Hook |
| **New User** | a member registers | `user.registered` REST Hook |

All three are true REST Hooks — Connect calls Zapier the instant the event
happens (no polling). If a Zap is turned off, Connect's endpoint returns `410`
and the subscription is pruned automatically.

## Actions

| Action | Does |
| --- | --- |
| **Create Discussion** | starts a discussion (optionally tagged) as the key's user |
| **Create Reply** | replies to a discussion as the key's user |

## Authentication

Two fields, filled in by the forum owner:

- **Forum URL** — e.g. `https://community.example.com`
- **Connect API key** — minted in **Admin → Connect → Create a key** (Read + Write)

Zapier verifies the key against `GET /api/connect/me` and labels the connection
with the forum's title.

## Developing

This app is built with the [Zapier Platform CLI](https://docs.zapier.com/platform/build-cli/overview).

```bash
npm install
npm install -g zapier-platform-cli
zapier login
zapier register "Connect for Flarum"   # first time only
zapier validate                          # static checks
zapier test                              # unit tests
zapier push                              # upload a new version
```

> REST Hook triggers need a **public HTTPS forum** Zapier can reach when you test
> them (a live site, or ngrok in front of your dev install).

## Publishing

Zapier's public directory requires an integration to reach **50 active users
with live Zaps** plus **10 Zap templates**, moving Private → Beta (90 days) →
Public. Embedding Zapier's connection UI in the Connect admin panel qualifies for
the embed waiver, which shortens that path. See the extension's notes for the
current plan.

MIT © Ernest Defoe
