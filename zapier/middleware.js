'use strict';

// Attach the Connect API key to every outgoing request as a Bearer token, and
// surface Connect's JSON:API-style errors as readable messages in the Zap editor.

const includeBearer = (request, z, bundle) => {
  if (bundle.authData && bundle.authData.apiKey) {
    request.headers = request.headers || {};
    request.headers.Authorization = `Bearer ${bundle.authData.apiKey}`;
  }
  return request;
};

const handleErrors = (response, z) => {
  // Let 410 (a removed hook) through — Zapier interprets it as "stop sending".
  if (response.status === 410) {
    return response;
  }
  if (response.status === 401) {
    throw new z.errors.RefreshAuthError('Your Connect API key is invalid or was revoked.');
  }
  if (response.status >= 400) {
    let detail = '';
    try {
      const body = response.json || JSON.parse(response.content);
      detail = (body.errors && body.errors[0] && (body.errors[0].detail || body.errors[0].code)) || '';
    } catch (e) {
      detail = (response.content || '').slice(0, 200);
    }
    throw new z.errors.Error(
      `The forum responded with ${response.status}${detail ? `: ${detail}` : ''}.`,
      'ConnectError',
      response.status
    );
  }
  return response;
};

module.exports = { includeBearer, handleErrors };
