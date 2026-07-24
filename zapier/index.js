'use strict';

const { version: platformVersion } = require('zapier-platform-core');

const { authentication } = require('./authentication');
const { includeBearer, handleErrors } = require('./middleware');

const newDiscussion = require('./triggers/newDiscussion');
const newPost = require('./triggers/newPost');
const newUser = require('./triggers/newUser');

// Hidden — these only back the dynamic dropdowns on actions.
const discussionList = require('./triggers/discussionList');
const tagList = require('./triggers/tagList');

const createDiscussion = require('./creates/createDiscussion');
const createPost = require('./creates/createPost');

const { version } = require('./package.json');

module.exports = {
  version,
  platformVersion,

  // Don't let the platform silently rewrite input before it reaches us — our
  // endpoints validate their own input, and predictable payloads are easier to
  // debug (Zapier check D028).
  flags: {
    cleanInputData: false,
  },

  authentication,

  // Every request carries the Bearer key; every response is checked for errors.
  beforeRequest: [includeBearer],
  afterResponse: [handleErrors],

  triggers: {
    [newDiscussion.key]: newDiscussion,
    [newPost.key]: newPost,
    [newUser.key]: newUser,
    [discussionList.key]: discussionList,
    [tagList.key]: tagList,
  },

  creates: {
    [createDiscussion.key]: createDiscussion,
    [createPost.key]: createPost,
  },

  searches: {},
};
