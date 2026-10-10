# Contributing to DayWright

Contributions to DayWright are welcome. Before opening an issue or pull request, please read this guide and our [Code of Conduct](CODE_OF_CONDUCT.md).

## Issues

- **Feature requests** need to describe as thoroughly as possible and perhaps contain some info on how you would implement it
- **Bug reports** need to be described in detail what the problem is, how it was triggered and perhaps contain a possible solution
- **Questions** are free to be asked about the internals of the codebase and about the project

## Pull Requests

We very much appreciate any help with [open issues labeled with "help wanted"](https://github.com/hamza094/daywright/issues?q=state%3Aopen%20label%3A%22help%20wanted%22).

- **Feature requests** we're welcoming pull requests for new features (although we might not accept every single one). You can also first discuss new feature requests [through an issue](https://github.com/hamza094/daywright/issues) before sending in a pull request
- **Bug fixes** should contain regression tests
- All pull requests should follow the [coding standards](#coding-standards)
- Pull requests are reviewed by the project maintainers before merging.
- Please be respectful to other contributors and hold to [The Code Manifesto](http://codemanifesto.com/)
- Please post screenshots if you make any changes to the UI

## Coding Standards

- It's a good practice to write tests for your contribution
- Write the full namespace in DocBlocks for `@param`, `@var` or `@return` tags
- GitHub Actions checks Laravel Pint, PHPStan/Larastan, Rector, PHPUnit, frontend linting, frontend tests, and the production build. Run the relevant checks locally before opening a pull request.

## Backend integrations and external side effects

Do not use an outbox for every endpoint. Choose the simplest design that preserves the required reliability:

- Local database changes only: use a normal database transaction.
- Read-only third-party requests: an outbox is usually unnecessary.
- Third-party writes that change remote state: use a durable outbox or operation record when losing or duplicating the request could cause inconsistency.
- Payments, subscriptions, provisioning, notifications, SMS, and destructive actions: prefer an outbox or durable operation record with retries and reconciliation.

For a reliable third-party write, follow this sequence:

1. In a short database transaction, validate fresh state and save the intended operation in an outbox or operation table.
2. Commit the transaction before making the network request.
3. Process the operation after commit, normally in a queue worker.
4. Mark it `completed`, `failed`, or `unknown` and retain enough data to retry or reconcile it.

Never hold a database transaction open during a third-party network request. The database and provider generally cannot be committed atomically together, so use provider idempotency keys when available, make retries safe, and treat timeouts as `unknown` rather than automatically failed.

For inbound webhooks, save the verified event before acknowledging it. Process it asynchronously through the webhook inbox, deduplicate by provider event ID or a deterministic fingerprint, and make the handler safe to run more than once. See [`docs/WEBHOOK_INBOX.md`](docs/WEBHOOK_INBOX.md) and [`docs/WEBHOOK_PROVIDER_ONBOARDING.md`](docs/WEBHOOK_PROVIDER_ONBOARDING.md).

## Testing

All tests can be run with the following commands.

    $ vendor/bin/phpunit
