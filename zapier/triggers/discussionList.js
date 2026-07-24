'use strict';

const { normalizeSite } = require('../authentication');

// Hidden trigger. Its only job is to populate the "which discussion?" dropdown
// on actions, so a Zap author picks a discussion by title instead of hunting
// for a numeric ID. Never shown in the trigger list.
const perform = async (z, bundle) => {
  const response = await z.request({
    url: `${normalizeSite(bundle.authData.siteUrl)}/api/connect/discussions`,
    method: 'GET',
    params: {
      page: (bundle.meta && bundle.meta.page) || 0,
    },
  });
  return response.data;
};

module.exports = {
  key: 'discussion_list',
  noun: 'Discussion',
  display: {
    label: 'List of Discussions',
    description: 'Internal — powers the discussion dropdown on actions.',
    hidden: true,
  },
  operation: {
    perform,
    canPaginate: true,
    sample: { id: 42, title: 'Welcome to the community!' },
    outputFields: [
      { key: 'id', label: 'Discussion ID', type: 'integer' },
      { key: 'title', label: 'Title' },
    ],
  },
};
