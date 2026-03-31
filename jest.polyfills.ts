// jest.polyfills.ts — runs via setupFiles before test modules are evaluated.
// Polyfills Web Fetch API globals (Request/Response/Headers/fetch) into the
// jsdom environment so that modules that extend Request (like next/server) work.
//
// We require the dist/index.cjs file directly (bypassing the package exports map
// which resolves to the browser ESM build in jsdom's "browser" condition).

/* eslint-disable @typescript-eslint/no-require-imports */
const nfn = require('./node_modules/node-fetch-native/dist/index.cjs') as {
  fetch: typeof globalThis.fetch
  Request: typeof globalThis.Request
  Response: typeof globalThis.Response
  Headers: typeof globalThis.Headers
}

if (typeof globalThis.fetch === 'undefined') globalThis.fetch = nfn.fetch
if (typeof globalThis.Request === 'undefined') globalThis.Request = nfn.Request
if (typeof globalThis.Response === 'undefined') globalThis.Response = nfn.Response
if (typeof globalThis.Headers === 'undefined') globalThis.Headers = nfn.Headers
