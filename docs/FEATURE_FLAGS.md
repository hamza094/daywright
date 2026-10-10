# Feature Flags

Feature flags control whether selected DayWright capabilities are available during rollout. They can also tell the web client which controls to display for the signed-in account.

## Current flags

| Capability        | Flag                | What it controls                        |
| ----------------- | ------------------- | --------------------------------------- |
| Project export    | `project_export`    | Exporting project data                  |
| Project messaging | `project_messaging` | Sending and scheduling project messages |

Both capabilities are currently enabled for administrator accounts by default. They are unavailable to other accounts unless the rollout rules change. Subscription requirements, project membership, token scopes, and other access checks still apply.

## How availability is reported

The signed-in user's response, including `/api/v1/users/me`, contains a `features` map. The web client uses it to show or hide relevant controls; a missing flag is treated as unavailable.

The map is informational for the client. The API enforces feature availability and authorization on protected operations, so hiding a control is not a security boundary.
