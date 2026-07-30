# RFC performance checks

These k6 scenarios are read-only. Run them against a local or dedicated
performance environment, not production, unless the environment owner has
approved the test window.

## Public pages

```bash
BASE_URL=http://rfc.test VUS=100 DURATION=60s THINK_TIME=1 \
  k6 run tests/Performance/public-pages.js
```

## Authenticated pages

Sign in normally, then provide the value of the Laravel session cookie. The
default cookie name is `rfc-session`.

```bash
BASE_URL=http://rfc.test \
SESSION_COOKIE_VALUE='encrypted-cookie-value' \
PATHS='/ar/control-panel,/ar/control-panel/applications' \
VUS=100 DURATION=60s THINK_TIME=1 \
  k6 run tests/Performance/authenticated-pages.js
```

Using one cookie across virtual users is suitable for read-path profiling, but
it is conservative because every request updates the same database session.
A final staging certification should use a pool of test accounts or session
cookies to model independent users.

## Interpreting concurrency

- `VUS=100 THINK_TIME=1` models 100 active users who pause briefly between pages.
- `VUS=100 THINK_TIME=0 DURATION=10s` is a short burst and is substantially
  harsher than 100 normal interactive users.
- The scripts fail when more than 1% of requests fail or checks do not pass.
- Public p95 must stay below 2 seconds. Authenticated p95 must stay below 3
  seconds.
