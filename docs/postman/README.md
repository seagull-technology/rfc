# RFC GSB Postman Collection

This folder contains a reusable Postman collection for RFC Government Service Bus integrations. It currently includes the MOHE Standard student lookup and will be extended one provider at a time.

## Import

1. Import `RFC-GSB.postman_collection.json` into Postman.
2. Copy `RFC-GSB-STG.postman_environment.example.json` to `RFC-GSB-STG.local.postman_environment.json`.
3. Fill the local file with the server-only client ID, client secret, and test identity.
4. Import the local environment into Postman and select it before running a request.

The `.local.postman_environment.json` pattern is ignored by Git.

## MOHE Request

- Method: `POST`
- URL: `{{gsb_base_url}}{{mohe_standard_path}}`
- Required body fields: `nationalNo`, `birthDate`
- Birth-date format: `YYYY-MM-DD`
- Required authentication headers: `X-MODEE-Client-Id`, `X-MODEE-Client-Secret`

The request validates required variables before sending and verifies that the service returns HTTP 200 with a non-empty JSON response.

## Security

- Never add real credentials or test identities to the collection.
- Never commit a local Postman environment.
- Keep Postman cloud synchronization disabled on the government server unless RFC explicitly approves it.
- Mask personal values before sharing response examples outside the protected environment.
