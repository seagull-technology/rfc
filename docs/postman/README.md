# RFC GSB Postman Collection

This folder contains reusable RFC Government Service Bus requests for MOHE and SANAD SignFlow.

## Import

1. Import `RFC-GSB.postman_collection.json` into Postman.
2. Copy `RFC-GSB-STG.postman_environment.example.json` to `RFC-GSB-STG.local.postman_environment.json`.
3. Fill the local file with the server-only client ID, client secret, and test identity.
4. Import the local environment into Postman and select it before running a request.

The `.local.postman_environment.json` pattern is ignored by Git.

## MOHE Request

- Method: `POST`
- URL: `{{gsb_base_url}}{{mohe_undergraduate_last_semester_path}}`
- Required body fields: `nationalNo`, `birthDate`
- Birth-date format: `YYYY-MM-DD`
- Required authentication headers: `X-MODEE-Client-Id`, `X-MODEE-Client-Secret`

The request validates required variables before sending and verifies that the service returns HTTP 200 with a non-empty JSON response.

## SANAD / SignFlow

Subscribe the RFC app to both `SignFlow.v2` and `SignFlow.v2.Open`, then configure `sanad_client_id` and `sanad_client_secret` in the local environment. Start with **SANAD - SignFlow > User Status** because it verifies gateway connectivity and subscription without requiring a browser authorization code.

Token and signing requests use these local secret values only when needed:

- `sanad_authorization_code`
- `sanad_pkce_verifier`
- `sanad_redirect_uri`
- `sanad_access_token`
- `sanad_refresh_token`
- `sanad_hash`

The supplied SignFlow contracts do not define the browser authorization URL. Obtain it from MODEE and have the RFC callback URL registered before configuring `SANAD_AUTHORIZATION_URL` in Laravel. Until then, the application's SANAD login control remains disabled, while the status diagnostic and direct API tests remain available.

## Security

- Never add real credentials or test identities to the collection.
- Never commit a local Postman environment.
- Keep Postman cloud synchronization disabled on the government server unless RFC explicitly approves it.
- Mask personal values before sharing response examples outside the protected environment.
- Never store SANAD authorization codes, PKCE verifiers, access/refresh tokens, hashes, or signatures in the collection.
