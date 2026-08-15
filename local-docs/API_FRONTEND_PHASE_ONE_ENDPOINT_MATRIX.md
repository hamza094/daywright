# API Frontend Phase 1 Endpoint Matrix

This matrix freezes the response parsing rules introduced in Phase 1.
Use the shared response parsers in `resources/js/utils/apiResponse.js` and `resources/js/utils/authResponse.js` for auth-related frontend work in this rollout.

## Shared Parser Rules

- Helper-backed resource responses: read `response.data.data` via `getResponseData(response)`.
- Native paginated resource responses: read top-level `data`, `meta`, and `links` via `getPaginatedData(response)`.
- Message-only success responses: read `response.data.message` via `getResponseMessage(response)`.
- Standardized API errors: read `message`, `code`, `errors`, and `meta` from `parseApiError(error)`.
- New frontend changes should prefer these parsers over direct legacy-key access such as `response.data.user`, `response.data.status`, or `response.data.error_type`.

## Contract Decisions

- The SPA login two-factor challenge remains wrapped under `data`. The contract is `{ data: { two_factor_state, message } }`.
- Direct session login and post-challenge session completion now expose `data.user` and `data.features` through the shared authenticated-session resource.
- Verification-specific UI should branch on the backend `message` values `verification.invalid` and `verification.already_verified` when it needs distinct UX. The generic `code` values remain useful for shared handling, but they are intentionally not granular enough to distinguish those two verification states on their own.

## Auth And Verification Matrix

| Endpoint                               | Contract type           | Success payload                                                                                                 | Notes                                                                    |
| -------------------------------------- | ----------------------- | --------------------------------------------------------------------------------------------------------------- | ------------------------------------------------------------------------ |
| `POST /api/v1/session/login`           | Helper-wrapped resource | `data.user` + `data.features` on direct login, or `data.two_factor_state` + `data.message` when 2FA is required | The 2FA challenge stays under `data`; do not read top-level `status`.    |
| `POST /api/v1/session/logout`          | Message-only            | `message`                                                                                                       | Session teardown response only.                                          |
| `GET /api/v1/users/me`                 | Helper-wrapped resource | `data.user`, `data.features`                                                                                    | Current-user bootstrap contract used by the SPA root store.              |
| `GET /api/v1/twofactor/fetch-user`     | Helper-wrapped resource | `data.two_factor_state` and, when pending setup, `data.qr_code`, `data.uri`, `data.string`                      | Used to render current 2FA state.                                        |
| `POST /api/v1/twofactor/setup`         | Helper-wrapped resource | `data.qr_code`, `data.uri`, `data.string`, `data.two_factor_state`                                              | Starts setup.                                                            |
| `POST /api/v1/twofactor/confirm`       | Helper-wrapped resource | `data.recovery_codes`, `data.two_factor_state`                                                                  | Returns recovery codes under snake_case.                                 |
| `POST /api/v1/twofactor/login-confirm` | Helper-wrapped resource | `data.user`, `data.features`                                                                                    | Completes browser login after the challenge step.                        |
| `GET /api/v1/twofactor/recovery-codes` | Helper-wrapped resource | `data.recovery_codes`                                                                                           | Recovery code regeneration/read flow.                                    |
| `DELETE /api/v1/twofactor/disable`     | Helper-wrapped resource | `data.two_factor_state`                                                                                         | Disable response stays wrapped; frontend uses local success copy.        |
| `POST /api/v1/email/verify/{user}`     | Helper-wrapped resource | `data.verified`                                                                                                 | Failure responses use standardized `{ message, code, errors, meta }`.    |
| `POST /api/v1/email/resend/{user}`     | Message-only            | `message`                                                                                                       | Validation failures come back as `validation_error` with `errors.email`. |
