# REST API Refactor Plan

## Objective

Refactor API to follow REST principles and proper HTTP semantics.

## Critical Rules

- No state-changing operations via GET
- Use POST, PATCH, PUT, DELETE for mutations
- Use nouns, not verbs
- Follow Laravel conventions

## Note

For each phase must check this

- [ ] Update routes
- [ ] Update controllers
- [ ] Update frontend calls
- [ ] Add form request validation if needed and missing
- [] Update status code if needed use laravel/symphony HTTP:: instead status code number

# Next Phase

GET /me -> Should be GET /users/me or GET /user
GET /user/token -> RPC-style, should be under /users/me/zoom-token
GET /user/jwt/token -> RPC-style
GET /tasksdata -> Concatenated noun+noun, should be GET /dashboard/tasks
GET /user/activities -> Should be GET /dashboard/activities or GET /users/me/activities
GET /user/dashboard-projects -> RPC-style, should be GET /dashboard/projects
GET /user/projects -> Should be under /projects (already exists) or /dashboard/projects
GET /task/statuses -> Singular noun, should be GET /task-statuses
GET pending/invitations -> Inconsistent nesting, should be GET /invitations?status=pending
DELETE messages/{message}/delete -> Redundant /delete suffix; DELETE messages/{message} is sufficient
