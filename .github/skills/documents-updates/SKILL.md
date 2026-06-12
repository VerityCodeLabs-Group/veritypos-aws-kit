---
name: documents-updates
description: >-
  Guides keeping the ./docs folder in sync with codebase changes. After implementing a feature,
  refactoring, or changing architecture, finds related documentation and updates it — or adds a
  dedicated section if no relevant doc exists.
  Activates when completing a feature, refactoring architecture, changing domain structure, or
  modifying behaviour covered by existing docs; or when the user mentions docs, documentation,
  update docs, or document changes.
---

# Documents Updates

## When to Apply

- Completing a feature that affects documented architecture or API surface
- Refactoring code that changes behaviour described in existing docs
- Adding new JWT claims, middleware, clients, or data DTOs
- Changing authentication flow, configuration options, or error handling
- User explicitly asks to update or create documentation

## Workflow

1. Identify what changed (JWT structure, middleware, clients, config, exceptions)
2. Find related doc if it exists
3. Update in place (keep structure/headings) or create a new file if nothing related exists
4. Documentation only created when explicitly requested by user

## Documentation Style

- `#` title, `##` major sections, `###` subsections
- Fenced code blocks with language identifiers
- Show namespace and imports in PHP examples
- Write for developers integrating the SDK
- Avoid duplication — link instead of copy

## Common Pitfalls

- Duplicating content across docs
- Leaving stale examples after claim name changes
- Creating docs for trivial changes
- Not documenting consumer impact
