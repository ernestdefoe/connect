'use strict';

// Connect is installed on each customer's own Flarum, so we can't use a shared
// OAuth app — auth is per-site. The user supplies their forum URL and a Connect
// API key (an admin mints it in Admin → Connect). We verify by calling
// GET /api/connect/me, which returns the user the key acts as.

const test = async (z, bundle) => {
  const response = await z.request({
    url: `${normalizeSite(bundle.authData.siteUrl)}/api/connect/me`,
    method: 'GET',
  });
  // /connect/me answers JSON:API-style: { data: { attributes: {...} } }.
  // Flatten to the attributes so connectionLabel can read them directly.
  const d = response.data;
  return d && d.data && d.data.attributes ? d.data.attributes : d; // { forumTitle, user, userId, keyLabel }
};

// A human-readable label for the connected account, shown in the Zap editor.
const connectionLabel = (z, bundle) => {
  const d = bundle.inputData || {};
  return d.forumTitle ? `${d.forumTitle} (as ${d.user || 'key'})` : d.user || 'Flarum';
};

// Strip a trailing slash so we can safely append /api/... everywhere.
const normalizeSite = (raw) => String(raw || '').trim().replace(/\/+$/, '');

module.exports = {
  authentication: {
    type: 'custom',
    test,
    connectionLabel,
    fields: [
      {
        key: 'siteUrl',
        label: 'Forum URL',
        type: 'string',
        required: true,
        helpText:
          'Your Flarum forum, e.g. **https://community.example.com** (no trailing slash needed).',
        placeholder: 'https://community.example.com',
      },
      {
        key: 'apiKey',
        label: 'Connect API key',
        type: 'password',
        required: true,
        helpText:
          'In your forum: **Admin → Connect → Create a key** (give it Read + Write). Paste the `ck_…` token here.',
      },
    ],
  },
  normalizeSite,
};
