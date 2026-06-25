// Global test setup for the Vitest (jsdom) suite.
//
// jsdom already provides window/document. Add any globals the app code expects
// on window here (mirrors the browser runtime) so module tests can run without
// a real page. Kept intentionally small — extend as the JS grows.
globalThis.alert = globalThis.alert ?? (() => {});
