'use strict';

const restHookTrigger = require('./hookFactory');

module.exports = restHookTrigger({
  key: 'new_user',
  event: 'user.registered',
  noun: 'User',
  label: 'New User',
  description: 'Triggers when a new member registers on your forum.',
  outputFields: [
    { key: 'id', label: 'User ID', type: 'integer' },
    { key: 'username', label: 'Username' },
    { key: 'name', label: 'Display Name' },
    { key: 'email', label: 'Email' },
    { key: 'url', label: 'Profile URL' },
    { key: 'createdAt', label: 'Registered At', type: 'datetime' },
  ],
  sample: {
    id: 51,
    username: 'katherine',
    name: 'Katherine Johnson',
    email: 'katherine@example.com',
    url: 'https://community.example.com/u/katherine',
    createdAt: '2026-07-23T12:10:00+00:00',
  },
});
