# VerityPOS AWS Kit for PHP

## Foundational Context

This is a **standalone PHP Composer package** (library), NOT a Laravel application. It provides reusable AWS integration patterns — EventBridge publishers/consumers, SQS workers, Lambda handlers, SQS/SNS envelopes — that are runtime-agnostic so the same code runs on Fargate, ECS, Lambda, and LocalStack. It is consumed by `veritypos-auth-service`, `veritypos-commerce-service`, `veritypos-platform-service`, and any future VerityPOS microservice that needs to talk to AWS.

**Current scope:** EventBridge publish + Lambda consumer + envelope parsing + runtime-agnostic Dispatcher/Handler/Envelope contracts. Future releases will add SQS workers, SNS pub/sub, CloudWatch metrics/alarms, EventBridge Scheduler, and X-Ray helpers.

### Stack

- php - 8.5+
- aws/aws-sdk-php - ^3.0
- bref/bref - ^3.0 (only needed if you also use the Lambda handler)
- illuminate/support - ^11.0|^12.0|^13.0
- illuminate/console - ^11.0|^12.0|^13.0
- illuminate/contracts - ^11.0|^12.0|^13.0
- illuminate/http - ^11.0|^12.0|^13.0
- veritypos/contracts - ^6.9 (shared DTOs across the org)
- pestphp/pest - ^3.0 (dev)
- phpstan/phpstan - ^2.0 (dev, level 8)
- laravel/pint - ^1.24 (dev)
- orchestra/testbench - ^10.0|^11.0 (dev)

## Skills Activation

This project has domain-specific skills available. You MUST activate the relevant skill whenever you work in that domain — don't wait until you're stuck.

- `pest-testing` — Guides testing with Pest 3. Activates when writing tests, adding assertions, or debugging test failures.
- `documents-updates` — Guides keeping the ./docs folder in sync with codebase changes — finds related docs and updates them, or adds a dedicated section if none exists. Activates when completing a feature, refactoring architecture, or changing domain structure; or when the user mentions docs, documentation, update docs, or document changes.
- `git-workflow` — Guides git operations — branching from main, semantic commits, pre-commit quality checks, and PR creation targeting main. Activates when performing git operations, creating branches, committing, pushing, creating PRs; or when the user mentions git, branch, commit, push, or PR.
- `learns-skills` — Guides creation and maintenance of AI skills — file format, directory structure, registration, and cross-repo distribution. Activates when creating a new skill or updating skill conventions.

## Architecture Guidelines

Read these guides at the start of every session or when working in these areas:

- **Package Structure**: `.ai/guidelines/package-structure/guide.md` — Directory layout, naming conventions, where to put new files, code conventions, DTO patterns, service provider registration, and testing structure.

## Application Structure

All source code lives in `src/` with the `VerityPOS\AwsKit\` namespace.

```
src/
├── Aws/               # Generic AWS SDK client factory (region, endpoint, creds)
├── Config/            # Publishable config (aws-kit.php)
├── Console/           # Artisan commands (CLI simulator, future worker commands)
├── Contracts/         # Runtime-agnostic interfaces (Envelope, Handler, Dispatcher, Consumer)
├── Dispatcher/        # Prefix-based event router (the runtime-agnostic core)
├── EventBridge/       # EventBridge publisher + envelope parser
│   └── Runtime/       # Runtime adapters (Lambda handler, CLI simulator)
└── Providers/         # AwsKitServiceProvider
```

## The 3 patterns

Every AWS integration in this package follows one of these patterns:

1. **Client Factory** — `Aws/ClientFactory::build($service, $region, $endpoint, $credentials)` returns a configured AWS SDK client. Used by every concrete service (EventBridge, SQS, SNS, CloudWatch, ...).
2. **Envelope Parser** — `Contracts/Envelope` is the runtime-agnostic shape. Each AWS protocol has a parser (e.g. `EventBridgeEnvelopeParser`) that unwraps the protocol-specific JSON into a uniform `Envelope`. Consumers don't know if the event came from EventBridge, SQS, or SNS.
3. **Runtime Adapter** — Each "where does the code run" target gets its own adapter. EventBridge has `Runtime/EventBridgeLambdaHandler` (Bref Lambda) and `Console/EventBridgeInvokeCommand` (CLI simulator). SQS will get `Console/SqsConsumeCommand` (long-poll worker for Fargate supervisord). All adapters call the same `Dispatcher::dispatch()`.

## Running Commands

This is a library — all commands run directly on the host (no Docker):

```bash
composer test           # Run all checks (lint + stan + unit)
composer test:lint      # Check formatting (pint --test)
composer test:lint:fix  # Fix formatting (pint)
composer test:stan      # PHPStan level 8
composer test:unit      # Pest tests
```

## Conventions

- You must follow all existing code conventions. When creating or editing a file, check sibling files for the correct structure, approach, and naming.
- Use descriptive names for variables and methods.
- Check for existing components to reuse before writing a new one.

## PHP

- Always use curly braces for control structures, even for single-line bodies.
- Use PHP 8 constructor property promotion.
- Always use explicit return type declarations and type hints.
- All classes must be `final` unless they are intentionally extensible (the `Contracts/*` interfaces are not final by design).
- All files must have `declare(strict_types=1);`.
- Prefer PHPDoc blocks over inline comments. Never use comments within the code itself unless the logic is exceptionally complex.
- Add useful array shape type definitions when appropriate.

## Code Quality

- **PHPStan level 8** — The strictest level. All code must pass `composer test:stan`.
- **Pint** — Laravel preset with `declare_strict_types` and `final_class` rules enforced. Run `vendor/bin/pint --dirty` before finalizing changes.
- **Pest 3** — Unit tests via Orchestra Testbench. Run `composer test:unit`.

## Test Enforcement

- Every change must be tested. Write a new test or update an existing test, then run the affected tests.
- Run the minimum tests needed: `pest --filter=TestName`.
- Tests live in `tests/Unit/` grouped by source directory (e.g., `tests/Unit/EventBridge/`, `tests/Unit/Dispatcher/`).

## Consumer Projects

This SDK is consumed as a Composer VCS dependency in service repos (added to `composer.json` as `"veritypos/aws-kit": "^0.1"`, with the GitHub VCS repo configured in `composer.json`).

| Consumer | Purpose |
|----------|---------|
| `veritypos-auth-service` | EventBridge publish (UserCreated/UserUpdated/UserDeleted) + Lambda handler (UserEventHandler) |
| `veritypos-commerce-service` | EventBridge publish (TenantProvisioned, BranchCreated, etc) + Lambda handler (TenantEventHandler, ProvisioningEventHandler) |
| `veritypos-platform-service` | EventBridge publish (TenantCreated, TenantUpdated, TenantProvisioning) + Lambda handler (UserEventHandler, ProvisioningEventHandler) |

Changes to this SDK affect all consumers. After pushing a release, consumers update their `composer require veritypos/aws-kit:^X.Y` to pick it up.

## Documentation

Comprehensive docs live in `docs/`:

- `docs/architecture.md` — The 3 patterns, runtime adapter model, why this package exists
- `docs/eventbridge.md` — EventBridge publisher + Lambda handler usage
- `docs/configuration.md` — Environment variables, service provider

## Documentation Files

- You must only create documentation files if explicitly requested by the user.

## Dependencies

- Do not change the package's dependencies without approval.

## Replies

- Be concise in your explanations - focus on what's important rather than explaining obvious details.
