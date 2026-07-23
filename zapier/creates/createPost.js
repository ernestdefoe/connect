'use strict';

const { normalizeSite } = require('../authentication');

// Reply to an existing discussion as the key's user.
const perform = async (z, bundle) => {
  const site = normalizeSite(bundle.authData.siteUrl);
  const response = await z.request({
    url: `${site}/api/connect/actions/posts`,
    method: 'POST',
    body: {
      discussionId: parseInt(bundle.inputData.discussionId, 10),
      content: bundle.inputData.content,
    },
  });
  return response.data.data; // { id, discussionId, url }
};

module.exports = {
  key: 'create_post',
  noun: 'Reply',
  display: {
    label: 'Create Reply',
    description: 'Posts a reply to an existing discussion, as the connected key\'s user.',
  },
  operation: {
    perform,
    inputFields: [
      {
        key: 'discussionId',
        label: 'Discussion ID',
        type: 'integer',
        required: true,
        helpText: 'The ID of the discussion to reply to (e.g. from a "New Discussion" trigger).',
      },
      { key: 'content', label: 'Content', type: 'text', required: true, helpText: 'The reply body. Markdown is supported.' },
    ],
    sample: {
      id: 1337,
      discussionId: 42,
      url: 'https://community.example.com/d/42/3',
    },
    outputFields: [
      { key: 'id', label: 'Post ID', type: 'integer' },
      { key: 'discussionId', label: 'Discussion ID', type: 'integer' },
      { key: 'url', label: 'URL' },
    ],
  },
};
