---
name: learns-skills
description: >-
  Guides creation and maintenance of AI skills — file format, directory structure, registration, and
  cross-repo distribution. Activates when creating a new skill, updating skill conventions, or adding
  domain patterns that should be codified; or when the user mentions skill, learns-skills, or SKILL.md.
---

# Learns Skills

## When to Apply

- Creating a new skill
- Updating an existing skill's conventions or examples
- Codifying a repeated domain pattern into a skill
- Distributing skills across repos or AI tool directories

## Skill File Format

YAML frontmatter + markdown body. Frontmatter: `name` (kebab-case, matches directory), `description` (includes activation triggers).

## Directory Structure

Every skill must exist in THREE directories per repo:

```
.claude/skills/{skill-name}/SKILL.md    # Claude Code
.cursor/skills/{skill-name}/SKILL.md    # Cursor
.github/skills/{skill-name}/SKILL.md    # GitHub Copilot
```

All three copies must be identical.

## Registration

Register in BOTH `CLAUDE.md` and `AGENTS.md` (kept identical) under `## Skills Activation`:

```markdown
- `my-skill` — Short description. Activates when [conditions].
```

## When to Create a Skill

Create when:
- A domain has repeated patterns for AI agents to follow consistently
- Cross-cutting conventions emerge across multiple files/features
- New infrastructure is added with specific usage rules
- You find yourself correcting the same mistake repeatedly

Do NOT create for: one-off tasks, general programming knowledge, or patterns already in an existing skill.

## Naming Conventions

- Directory: `kebab-case`
- File: always `SKILL.md` (uppercase)
- Frontmatter `name`: must match directory exactly

## Cross-Repo Awareness

Known VerityPOS repos:

| Repo | Path |
|------|------|
| `veritypos-commerce-service` | `/Users/jovertical/Documents/Code/veritypos/veritypos-commerce-service` |
| `veritypos-auth-service` | `/Users/jovertical/Documents/Code/veritypos/veritypos-auth-service` |
| `veritypos-sync-sdk-php` | `/Users/jovertical/Documents/Code/veritypos/veritypos-sync-sdk-php` |

## Common Pitfalls

- Missing one of the three directories
- Not registering in CLAUDE.md and AGENTS.md
- Keeping CLAUDE.md and AGENTS.md out of sync
- Making the skill too broad
- No code snippets
- Forgetting cross-repo distribution
