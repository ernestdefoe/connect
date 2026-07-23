'use strict';

const restHookTrigger = require('./hookFactory');

module.exports = restHookTrigger({
  key: 'new_discussion',
  event: 'discussion.created',
  noun: 'Discussion',
  label: 'New Discussion',
  description: 'Triggers when a new discussion is started on your forum.',
  outputFields: [
    { key: 'id', label: 'Discussion ID', type: 'integer' },
    { key: 'title', label: 'Title' },
    { key: 'slug', label: 'Slug' },
    { key: 'url', label: 'URL' },
    { key: 'author', label: 'Author' },
    { key: 'authorId', label: 'Author ID', type: 'integer' },
    { key: 'tagList', label: 'Tags (comma-separated slugs)' },
    { key: 'createdAt', label: 'Created At', type: 'datetime' },
  ],
  sample: {
    id: 42,
    title: 'Welcome to the community!',
    slug: '42-welcome-to-the-community',
    url: 'https://community.example.com/d/42-welcome-to-the-community',
    author: 'Ada Lovelace',
    authorId: 7,
    tagList: 'announcements,general',
    createdAt: '2026-07-23T12:00:00+00:00',
  },
});
