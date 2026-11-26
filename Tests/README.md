# UI5 facade tests

## Widget setup storage

Serve the installation's `vendor/` directory on a loopback-only static server and open
[`JavaScript/SetupStorage.html`](JavaScript/SetupStorage.html). Run this in the browser console:

```javascript
await (await import('./UI5SetupStorage.mjs')).runUI5SetupStorageRegression()
```

The test uses a temporary database and covers installation isolation, legacy import, resets,
concurrent operations and transaction rollback without touching existing preferences.
