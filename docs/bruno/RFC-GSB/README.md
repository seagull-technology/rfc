# RFC GSB Bruno Collection

This collection is designed to run fully offline on the RFC government server. Bruno stores the collection locally and does not require an account.

## First Run

1. Start Bruno.
2. Select **Open Collection** and choose this `RFC-GSB` folder.
3. At the top-right, open **No Environment** and select **STG**.
4. Open the environment configuration and enter values for:
   - `gsb_client_id`
   - `gsb_client_secret`
   - `sanad_client_id`
   - `sanad_client_secret`
   - `test_national_no` (exactly 10 digits)
   - `test_birth_date` (`YYYY-MM-DD`)
   - `test_company_national_no` (numeric establishment national number, up to 10 digits)
5. Save the environment values.
6. Open **MOHE > Lookup Undergraduate Student - Last Semester**.
7. Select **Send**.

## Company Lookup Test

1. Configure `test_company_national_no` with the establishment national number. It may contain fewer than 10 digits.
2. Open **COMPANIES > Lookup Company - CCD** and select **Send**.
3. Open **COMPANIES > Lookup Individual Registry - MIT** and select **Send**.

An establishment may be registered in only one provider. The RFC application uses CCD first and automatically tries MIT when CCD returns no usable company record. Keep both raw responses for the first known test numbers so field aliases can be confirmed against real staging data.

The endpoint URL is fixed in the request, so an empty environment cannot produce an invalid URL. The environment contains only credentials and test identity values.

## SANAD / SignFlow

Both `SignFlow.v2` and `SignFlow.v2.Open` must be subscribed for the RFC application. Configure the SANAD product credentials separately, even when the provider currently issued the same values as the general GSB credentials.

1. Start with **SANAD > User Status**. It needs only `test_national_no` and confirms product subscription, network access, TLS, and IBM credentials.
2. The authorization-code request can be tested only after MODEE provides and registers the SANAD browser authorization URL and RFC callback URL.
3. For token operations, configure only the values required by that request:
   - `sanad_authorization_code`
   - `sanad_pkce_verifier`
   - `sanad_redirect_uri`
   - `sanad_access_token`
   - `sanad_refresh_token`
   - `sanad_hash`
4. Authorization codes and access tokens are short lived. Never reuse or share them in screenshots.

The supplied OpenAPI contracts do not contain the browser authorization endpoint. The application therefore keeps the SANAD login control disabled until `SANAD_AUTHORIZATION_URL` is explicitly configured. It does not guess this URL.

## Security

- Keep the environment values secret and local to the government server.
- Mask personal data before sharing a response.
- Keep the collection on the government server.
- Do not move real credentials into request files.
- Do not store SANAD authorization codes, PKCE verifiers, tokens, hashes, or signatures in Git.
