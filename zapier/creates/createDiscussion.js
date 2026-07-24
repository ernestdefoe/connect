'use strict';

const { normalizeSite } = require('../authentication');

// Start a new discussion as the key's user, through Connect's action endpoint
// (which posts via Flarum's own API, so permissions + validation still apply).
const perform = async (z, bundle) => {
  const site = normalizeSite(bundle.authData.siteUrl);
  const tags = (bundle.inputData.tags || [])
    .map((t) => parseInt(t, 10))
    .filter((n) => Number.isInteger(n));

  const response = await z.request({
    url: `${site}/api/connect/actions/discussions`,
    method: 'POST',
    body: {
      title: bundle.inputData.title,
      content: bundle.inputData.content,
      ...(tags.length ? { tags } : {}),
    },
  });
  return response.data.data; // { id, title, slug, url }
};

module.exports = {
  key: 'create_discussion',
  noun: 'Discussion',
  display: {
    label: 'Create Discussion',
    description: 'Starts a new discussion on your forum, posted as the connected key\'s user.',
  },
  operation: {
    perform,
    inputFields: [
      { key: 'title', label: 'Title', type: 'string', required: true },
      { key: 'content', label: 'Content', type: 'text', required: true, helpText: 'The body of the first post. Markdown is supported.' },
      {
        key: 'tags',
        label: 'Tags',
        list: true,
        required: false,
        dynamic: 'tag_list.id.name',
        helpText: 'Optional. Requires the Tags extension — forums without it can leave this empty.',
      },
    ],
    sample: {
      id: 42,
      title: 'Posted from Zapier',
      slug: '42-posted-from-zapier',
      url: 'https://community.example.com/d/42-posted-from-zapier',
    },
    outputFields: [
      { key: 'id', label: 'Discussion ID', type: 'integer' },
      { key: 'title', label: 'Title' },
      { key: 'slug', label: 'Slug' },
      { key: 'url', label: 'URL' },
    ],
  },
};
