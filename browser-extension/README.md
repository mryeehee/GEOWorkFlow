# GEOWorkFlow Chrome Operator

Manifest V3 extension for human-confirmed publishing work. It connects to a self-hosted GEOWorkFlow instance, claims assigned manual-publication work orders, opens target pages, and fills supported editors. The operator reviews the draft and performs the final publish action.

## Local installation

1. Run the GEOWorkFlow migration and sign in to the admin console.
2. Open `chrome://extensions`, enable Developer mode, and choose **Load unpacked**.
3. Select this `browser-extension` directory.
4. Open the toolbar action, enter the GEOWorkFlow base URL, and approve the displayed code in GEOWorkFlow.

Remote GEOWorkFlow instances require HTTPS. HTTP is accepted only for `localhost` and `127.0.0.1`.

One Chrome profile connects to one GEOWorkFlow instance. Use separate Chrome profiles when platform accounts must stay isolated.
After Chrome or the side panel restarts, reopen a claimed work order from the queue to restore its in-session context and heartbeat.

## Supported behavior

- Generic work orders: claim, open target, copy content, release, and report result.
- Zhihu answers: verify the active profile, locate the answer editor, and fill plain text.
- The extension never clicks the final Publish button.
- Platform cookies, passwords, and access tokens remain in Chrome and are never sent to GEOWorkFlow.

## Verification and packaging

```bash
npm run test:browser-extension
browser-extension/scripts/package.sh
```

The package script creates a reviewable ZIP under `dist/browser-extension` by default.
