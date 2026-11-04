# 0044. The dashboard on the home page, asked every 30 seconds

- Status: Accepted
- Date: 2026-11-04

## Context

E07-04 gave the API one endpoint with the operating figures (ADR 0043). E07-05 shows them. Three things had to be decided: where, for whom and how fresh.

## Decisions

- **On the home page, for supervisors and admins.** The people who run the warehouse open the app and want the figures first, so they are the start page and not one more entry in the menu. An operator keeps the welcome line: the API refuses `GET /api/kpis` below supervisor, so the screen does not ask for it (`hasRole` decides what to draw, the API has the last word).
- **Three periods counted back from now: 24 hours, 7 days and 30 days.** The browser sends only `from`, in ISO 8601 without milliseconds (the API refuses a fraction of a second), and the API ends the period at its own now. The choice is local state of the screen and not part of the address: it is a view, not a place.
- **Polling every 30 s, while the screen is open.** TanStack Query's `refetchInterval` asks again and does not poll in a background tab. The figures on the screen stay while a new period loads. When Mercure arrives (E07-06) it can invalidate the same query and the interval can grow, with no change to the screen. The figures are not saved for offline use: an old dashboard is worse than none, so the cache persister does not keep them.
- **A ratio the API leaves out is a dash with its reason.** "No list completed" is not zero lines per hour, so the card shows `—` and "No data in the period" instead of a number that would look like a bad result.
- **No thresholds.** A card shows the figure and what it is made of (units and lines for the short picks, locations for the occupancy) and does not paint it green or red: what is a good short-pick rate is the operation's call, not the screen's.

## Consequences

- Charts of the evolution need daily series that the endpoint does not give; they would extend ADR 0043's contracts.
- Every supervisor with the home page open makes a request every 30 s. Each one is a handful of aggregate queries (ADR 0043); watch it when many people have it open.
- The types of the answer come from the OpenAPI document (`npm run api:types`), so a change in the endpoint breaks the build and not the screen.
