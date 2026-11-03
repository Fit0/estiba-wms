# 0042. Picking without a connection: a queue in IndexedDB

- Status: Accepted
- Date: 2026-11-03

## Context

A warehouse has dead zones: an aisle behind a metal rack, a cold room. An operator who is walking a list must not stop because the phone lost the network, and must not lose what they confirmed. ADR 0040 made the app open without a connection (the shell is in the service worker); ADR 0041 made the stops confirmable one by one. What is missing is the data.

## Decisions

- **Only confirming is offline.** The operator starts the list with a connection (the API gives the list to whoever starts it, so two phones cannot take the same one) and may then confirm stops with or without one. Starting a list without a connection is not offered: it would turn a race for the list into a conflict found hours later.
- **A queue in IndexedDB, in order.** A confirmation that cannot reach the API (the browser says it is offline, or the request fails for lack of network or because a proxy answers 502, 503 or 504) goes to `ConfirmationQueue`, which saves each entry as it arrives. `ConfirmationSync` sends them one at a time, in the order they were made, when the app opens, when the browser says the network is back and every 15 s while something waits (the browser's "online" is not a promise that the API is reachable). The first failure of the connection stops the run: the rest would fail too and the order matters. The `idb` library is a thin wrapper over IndexedDB; the tests use `fake-indexeddb`.
- **Applied on top, never mixed in.** The screen shows the API's list with the pending confirmations applied (`applyQueued`). The cache is not edited, so the list that comes back from the API every 15 s cannot undo what the operator did, and when a confirmation is sent the API's list already has it. A list whose last stop is queued shows as completed.
- **What the API refuses stays visible.** A 4xx while sending (the scan did not match, the list was taken by someone else) does not stop the others: each stop is its own. The entry stays marked as refused with the API's sentence, the stop comes back as to do, and the operator can discard the entry. A stop the API says it already has counts as sent: the first try got through and only its answer was lost, so a retry is safe.
- **The queue belongs to the user.** Each entry keeps the user who made it, and only that user's entries are shown and sent. A 401 that renewing the session cannot fix stops the run and keeps everything for the next sign-in.
- **The data stays on the device.** The query cache of the pick lists and the products is saved with the TanStack Query persister (IndexedDB, 24 h, dropped when the session ends), so the list and the product names open without a connection. Without a saved list the screen says so instead of waiting for ever.
- **The app opens without a connection as the last user.** The session lives in a refresh cookie that needs the API. When the renewal fails because there is no network, `AuthClient` opens the user it remembers (who they are, their roles and their tenant, in `localStorage`; never a token) and the requests renew the session when they can. Anything the API refuses is still refused: the roles in the hint only decide what to draw. The hint is erased on sign-out and when the API refuses the session.
- **Offline, the barcode is not checked.** The phone compares the location and the lot (their text is on the screen) but the product only the API can compare. An offline confirmation with the wrong product is found when it is sent and comes back as refused.

## Consequences

- The picking screens show how many confirmations wait and which were refused; a notice says when a run sent or was refused something.
- The operator is the one who knows what to do with a refusal; the supervisor sees the list as it was when the API accepted things (short picks, completion events) only after the sync. Until then the dashboard lags, by design.
- The Background Sync API is not used: it works in Chromium only. The retries above work in every browser while the app is open; a closed app sends nothing until it is opened.
- A device shared by two operators keeps each one's queue apart, but the cache of lists is the same tenant's data; it is dropped on sign-out.
- A new shape of the cached data needs the `buster` of the persister bumped.
