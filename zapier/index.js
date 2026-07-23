'use strict';

const { version: platformVersion } = require('zapier-platform-core');

const { authentication } = require('./authentication');
const { includeBearer, handleErrors } = require('./middleware');

const newDiscussion = require('./triggers/newDiscussion');
const newPost = require('./triggers/newPost');
const newUser = require('./triggers/newUser');

const createDiscussion = require('./creates/createDiscussion');
const createPost = require('./creates/createPost');

const { version } = require('./package.json');

module.exports = {
  version,
  platformVersion,

  authentication,

  // Every request carries the Bearer key; every response is checked for errors.
  beforeRequest: [includeBearer],
  afterResponse: [handleErrors],

  triggers: {
    [newDiscussion.key]: newDiscussion,
    [newPost.key]: newPost,
    [newUser.key]: newUser,
  },

  creates: {
    [createDiscussion.key]: createDiscussion,
    [createPost.key]: createPost,
  },

  searches: {},
};
