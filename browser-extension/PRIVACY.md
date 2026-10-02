# GEOWorkFlow Chrome Operator Privacy Notice

Last updated: 2026-08-24

GEOWorkFlow Chrome Operator connects only to the GEOWorkFlow instance and publishing websites that the user explicitly authorizes.

## Data handled

- GEOWorkFlow connection URL and a scoped browser access token.
- Assigned work-order content, target URL, account profile URL, and execution status.
- The profile URL observed on a supported publishing page, used locally to prevent operation under the wrong account.
- A completion receipt containing status, result URL, client versions, timestamps, target origin, and, when verified by a packaged adapter, a hash of the profile URL observed on the page.

## Storage and transmission

- The scoped GEOWorkFlow token is stored in Chrome local extension storage restricted to trusted extension contexts.
- Active work-order content is stored in Chrome session storage and is cleared when the browser session ends or the task is resolved.
- Work-order data and completion receipts are exchanged directly with the user-configured GEOWorkFlow instance.
- Platform cookies, passwords, request headers, page bodies, and DOM snapshots are not collected or transmitted.

## Use and sharing

Data is used only to display assigned work, fill an operator-approved draft, prevent wrong-account actions, and record the operator's result. The extension does not sell data, use it for advertising, or transmit it to the GEOWorkFlow project maintainers.

Self-hosted GEOWorkFlow administrators control their server-side retention and access policies.

## Control and deletion

Users can disconnect the extension from the side panel or revoke a browser connection from GEOWorkFlow account settings. Disconnecting deletes the local token and active task state. Server-side work-order and audit records remain subject to the configured GEOWorkFlow retention policy.

Support and privacy questions: <https://github.com/mryeehee/GEOWorkFlow/issues>
