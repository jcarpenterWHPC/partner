// Shared fixtures for the Partner Portal Playwright tests.
//
// The app emits absolute https URLs for its baseURL and the box's Apache serves plain
// http on :80 (TLS ends at CloudFront), so every request to the app host is fulfilled
// from 127.0.0.1:80 with the app's own Host header and X-Forwarded-Proto: https — the
// same shape the ALB presents. Cookies and redirects then behave as in production.
const base = require('@playwright/test');
const http = require('http');

const LOCAL_HOST = '127.0.0.1';
const LOCAL_PORT = Number(process.env.WHPC_E2E_PORT || 80);
const APP_HOST = new URL(process.env.WHPC_E2E_BASE || 'https://partner-test.whpc.com').hostname;

function fetchLocal(pathQS, method, headers, body) {
  const h = { ...headers, host: APP_HOST, 'accept-encoding': 'identity', 'x-forwarded-proto': 'https' };
  for (const k of [':authority', ':method', ':path', ':scheme']) delete h[k];
  return new Promise((resolve, reject) => {
    const req = http.request({ host: LOCAL_HOST, port: LOCAL_PORT, path: pathQS, method, headers: h }, (res) => {
      const chunks = [];
      res.on('data', (c) => chunks.push(c));
      res.on('end', () => resolve({ status: res.statusCode, headers: res.headers, body: Buffer.concat(chunks) }));
    });
    req.on('error', reject);
    if (body && method !== 'GET' && method !== 'HEAD') req.write(body);
    req.end();
  });
}

async function installLocalRouting(context) {
  await context.route('**/*', async (route) => {
    let u;
    try { u = new URL(route.request().url()); } catch { return route.continue(); }
    if (u.hostname !== APP_HOST) return route.continue();
    try {
      const r = await fetchLocal(u.pathname + u.search, route.request().method(), route.request().headers(), route.request().postDataBuffer());
      const headers = { ...r.headers };
      for (const k of ['content-length', 'transfer-encoding', 'content-encoding']) delete headers[k];
      // Session regenerate emits several Set-Cookie lines; the last one is the live session.
      if (Array.isArray(headers['set-cookie'])) headers['set-cookie'] = headers['set-cookie'][headers['set-cookie'].length - 1];
      return route.fulfill({ status: r.status, headers, body: r.body });
    } catch (e) {
      return route.abort();
    }
  });
}

const test = base.test.extend({
  context: async ({ context }, use) => {
    await installLocalRouting(context);
    await use(context);
  },
});

module.exports = { test, expect: base.expect, APP_HOST };
