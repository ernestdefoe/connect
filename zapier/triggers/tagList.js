'use strict';

const { normalizeSite } = require('../authentication');

// Hidden trigger backing the tag dropdown on "Create Discussion". Forums
// without flarum/tags simply return an empty list, which leaves the optional
// field harmlessly empty rather than erroring.
const perform = async (z, bundle) => {
  const response = await z.request({
    url: `${normalizeSite(bundle.authData.siteUrl)}/api/connect/tags`,
    method: 'GET',
  });
  return response.data;
};

module.exports = {
  key: 'tag_list',
  noun: 'Tag',
  display: {
    label: 'List of Tags',
    description: 'Internal — powers the tag dropdown on actions.',
    hidden: true,
  },
  operation: {
    perform,
    sample: { id: 1, name: 'General' },
    outputFields: [
      { key: 'id', label: 'Tag ID', type: 'integer' },
      { key: 'name', label: 'Name' },
    ],
  },
};
