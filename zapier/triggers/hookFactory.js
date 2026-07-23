'use strict';

const { normalizeSite } = require('../authentication');

// Every Connect trigger is a REST Hook with the same four operations — only the
// event key, output fields, and sample differ. This factory builds one from a
// small config so new triggers are a few lines.
//
// config = { key, event, noun, label, description, outputFields, sample }
module.exports = function restHookTrigger(config) {
  const site = (bundle) => normalizeSite(bundle.authData.siteUrl);

  // Zapier hands us a target URL and asks Connect to POST future events to it.
  const performSubscribe = async (z, bundle) => {
    const response = await z.request({
      url: `${site(bundle)}/api/connect/hooks`,
      method: 'POST',
      body: {
        event: config.event,
        targetUrl: bundle.targetUrl,
        zapId: bundle.meta && bundle.meta.zap ? bundle.meta.zap.id : undefined,
      },
    });
    return response.data; // { id, event } — kept as bundle.subscribeData
  };

  const performUnsubscribe = async (z, bundle) => {
    const id = bundle.subscribeData && bundle.subscribeData.id;
    if (!id) return {};
    const response = await z.request({
      url: `${site(bundle)}/api/connect/hooks/${id}`,
      method: 'DELETE',
    });
    return response.data || {};
  };

  // Inbound webhook → Zapier. Must return an array whose shape matches
  // performList exactly.
  const perform = (z, bundle) => [bundle.cleanedRequest];

  // Pull a few recent real items so the user can map fields at setup time,
  // and so the trigger works before any live event fires.
  const performList = async (z, bundle) => {
    const response = await z.request({
      url: `${site(bundle)}/api/connect/samples/${config.event}`,
      method: 'GET',
    });
    return response.data;
  };

  return {
    key: config.key,
    noun: config.noun,
    display: {
      label: config.label,
      description: config.description,
    },
    operation: {
      type: 'hook',
      performSubscribe,
      performUnsubscribe,
      perform,
      performList,
      sample: config.sample,
      outputFields: config.outputFields,
    },
  };
};
