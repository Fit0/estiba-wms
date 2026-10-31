# 0041. Guided picking and barcode scanning

- Status: Accepted
- Date: 2026-10-30

## Context

The operator walks a pick list with a phone (ADR 0040). The API already checks every stop: it asks for the label of the location, the barcode of the product, the lot when the line has one, and how many units were taken, with a reason when they are fewer (OUT-10, OUT-11, OUT-12). The screen has to make that fast with gloves on, with a scanner that acts as a keyboard or with the camera.

## Decisions

- **One stop at a time.** `/picking/:id` starts the list (`POST /start` puts it in the name of the operator) and then shows the first stop that is not confirmed: the location in big type, the product, the units and the lot. Confirming a stop answers with the list, so the next stop appears without another request. A list of another operator is shown read-only; a completed one says how many stops were short.
- **Three ways to enter a code, one field.** `ScanField` is a text field: typing, pasting and a scanner that types the code and presses Enter all work. In the stop form Enter moves to the next field and confirms from the last. A camera button opens `CameraScanner` where the browser has a camera; it closes by itself on the first code and releases the camera.
- **`BarcodeDetector` first, ZXing as the fallback.** Chrome and Android read the code natively. Elsewhere `@zxing/browser` reads frames drawn on a canvas. ZXing is imported dynamically, so it is two extra files that nobody downloads until the camera opens on a browser that needs them (the service worker does precache them, so the camera also works offline).
- **Light checks on the phone, the real ones in the API.** The location and the lot are compared on the phone because their text is on the screen, and the units must be a whole number from 0 to what was asked, with a reason when fewer. The product is compared only by the API (the line carries the id, not the barcodes). What the API refuses is shown in the form with the translated sentence and nothing the operator typed is lost.
- **The lines are read, not trusted.** The OpenAPI document types `lines` as `unknown[]`, so `pickLines` reads each stop and gives it a type. A better schema in the backend would make this unnecessary.
- **The camera is tested for real in Chromium.** The tests start Chromium with a fake camera that plays `e2e/fixtures/ean13.mjpeg` (the EAN-13 8412345678905) and check that the code lands in the field. Firefox cannot play a file as a camera, so that spec is Chromium only; the rest of the picking flow runs in both engines with typed codes.

## Consequences

- The first stop needs no more than a scan of the shelf and of the product: 2 scans and 1 tap per stop.
- Taking a list is `start`, which fails if another operator took it first; the screen shows the API's message.
- Offline confirmation is not here: a confirmation that fails for lack of connection is an error the operator retries. The queue in IndexedDB is E07-03.
- Reading with the camera depends on light and focus; typing the code remains possible always.
