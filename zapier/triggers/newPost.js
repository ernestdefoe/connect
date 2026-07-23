'use strict';

const restHookTrigger = require('./hookFactory');

module.exports = restHookTrigger({
  key: 'new_post',
  event: 'post.created',
  noun: 'Reply',
  label: 'New Reply',
  description: 'Triggers when a new reply is posted to a discussion.',
  outputFields: [
    { key: 'id', label: 'Post ID', type: 'integer' },
    { key: 'discussionId', label: 'Discussion ID', type: 'integer' },
    { key: 'url', label: 'URL' },
    { key: 'content', label: 'Content' },
    { key: 'author', label: 'Author' },
    { key: 'authorId', label: 'Author ID', type: 'integer' },
    { key: 'createdAt', label: 'Created At', type: 'datetime' },
  ],
  sample: {
    id: 1337,
    discussionId: 42,
    url: 'https://community.example.com/d/42/3',
    content: 'Great to be here — thanks for the warm welcome!',
    author: 'Grace Hopper',
    authorId: 9,
    createdAt: '2026-07-23T12:05:00+00:00',
  },
});
