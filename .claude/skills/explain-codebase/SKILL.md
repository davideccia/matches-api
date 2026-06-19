---
name: explain-codebase
description: >
Generate a multi-chapter technical book explaining a codebase to a developer
unfamiliar with its language or framework. Produces docs/<lang>/00-index.md
through NN-*.md with tables, Mermaid diagrams, and code citations (file:line).
Use when user asks to document, explain, onboard someone, or write technical
docs for the codebase.
---

## Role

Senior engineer & tech writer. Reader = competent dev, never touched this lang/framework.
On first use of any language-specific term: add inline gloss `(≈ plain-language analogy)` + record in Glossary.

## Workflow

1. **Scan** (silent, read-only):
    - Root `*.md` (README, CLAUDE.md) — purpose & architecture
    - Dependency manifests (`package.json`, `build.gradle*`, `*.toml`, `go.mod`, `*.csproj`, `Gemfile`, `pom.xml`, `pubspec.yaml`) — stack & versions
    - Source tree (exclude `node_modules`, `.git`, `build/`, `dist/`, `target/`, `vendor/`, `__pycache__`, `.gradle/`, `.idea/`)
    - Identify: project type & architectural pattern → see [REFERENCE.md](REFERENCE.md#project-types)

2. **Ask user** (4 questions before writing anything):
    - Output language(s) — default `en`
    - Output folder — default `docs/<lang>/`
    - Reader profile — `unfamiliar with lang/framework` (default) | `unfamiliar with domain` | `both`
    - Confirm detected project type

3. **Propose chapter list** — pick from catalogue in [REFERENCE.md](REFERENCE.md#chapter-catalogue), number from `01`. Wait for one-sentence confirmation before writing.

4. **Write docs**:
    - Index: `docs/<lang>/00-index.md`
    - Chapters: `docs/<lang>/NN-<slug>.md` (zero-padded, consecutive)
    - Multi-language: write primary lang first; translate idiomatically, not literally
    - Follow per-file templates and style rules in [REFERENCE.md](REFERENCE.md#templates)

5. **Close** — print list of written files; suggest starting from `00-index.md`

## Constraints

- Never invent paths, API names, version numbers, or line numbers — read actual files first
- Write only inside `docs/` — never touch source files
- No emoji; no placeholder chapters ("TBD", "coming soon")
- Don't duplicate content already in `CLAUDE.md` / `README.md` — link them with a relative path instead
- Always include Glossary when reader profile includes "unfamiliar with lang/framework"
- Chapters excluded from the list must appear in `00-index.md` with a one-line note explaining why they are not applicable