# Middleware Audit for Public API

## Token Ability Middleware

Found `tokenAbility:*` middleware usage in public API routes:

### Projects Core (`routes/api/v1/projects/core.php`)

- `tokenAbility:projects:read` on GET `/insights`

### Projects Meetings (`routes/api/v1/projects/meetings.php`)

- `tokenAbility:projects:read` on meetings index/show
- `tokenAbility:projects:write` on meetings store/update/destroy
- `tokenAbility:projects:write` on zoom-tokens start
- `tokenAbility:projects:read` on zoom-tokens join

### Projects Messages (`routes/api/v1/projects/messages.php`)

- `tokenAbility:projects:write` on messages destroy
- `tokenAbility:projects:read` on conversations index
- `tokenAbility:projects:write` on conversations store/destroy

### Projects Tasks (`routes/api/v1/projects/tasks.php`)

- `tokenAbility:projects:read` on tasks index/show
- `tokenAbility:projects:write` on tasks store/update/destroy
- `tokenAbility:projects:write` on task assign/unassign
- `tokenAbility:projects:write` on task archive

## Policy Middleware

Found `can:*` middleware usage in public API routes:

### Projects Core (`routes/api/v1/projects/core.php`)

- `can:access,project` on GET `/insights`
- `can:access,project` group for activities
- `can:manage,project` on force delete

### Projects Meetings (`routes/api/v1/projects/meetings.php`)

- `can:access,project` on meetings index/show
- `can:manage,project` on meetings store/update/destroy
- `can:access,project` on zoom-tokens start
- `can:access,project` on zoom-tokens join

### Projects Messages (`routes/api/v1/projects/messages.php`)

- `can:access,project` group for messages
- `can:manage,project` on messages destroy
- `can:access,project` on conversations
- `can:delete,conversation` on conversations destroy

### Projects Tasks (`routes/api/v1/projects/tasks.php`)

- `can:access,project` group for tasks
- `can:manage,task` group for assign/unassign
- `can:access,task` group for archive

## Throttle Middleware

Found `throttle:*` middleware usage:

### Projects Messages (`routes/api/v1/projects/messages.php`)

- `throttle:sensitive-destructive` on messages destroy
- `throttle:sensitive-upload` on conversations store
- `throttle:sensitive-destructive` on conversations destroy

## Summary

**Token abilities requiring 403 documentation:**

- `projects:read`
- `projects:write`

**Policies requiring 403 documentation:**

- `access,project`
- `manage,project`
- `manage,task`
- `access,task`
- `delete,conversation`

**Throttling requiring 429 documentation:**

- `sensitive-destructive`
- `sensitive-upload`

## Implementation Notes

The middleware transformer must handle:

1. Aliases: `tokenAbility:*` vs `CheckTokenAbilities:*`
2. Parameterized middleware: `can:access,project`, `can:manage,project`, etc.
3. Resolved class names: `Illuminate\Auth\Middleware\Authorize`
4. Multiple middleware on same route
