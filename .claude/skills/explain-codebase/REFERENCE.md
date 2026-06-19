# explain-codebase — Reference

## Project Types

Pick the closest match (or combine) after the scan:

| Type               | Examples                           |
|--------------------|------------------------------------|
| Mobile app         | Android, iOS, Flutter, KMP         |
| Web SPA            | React, Vue, Angular                |
| Backend API        | REST/gRPC server, Spring, FastAPI  |
| Library/SDK        | published package, shared module   |
| CLI tool           | terminal utility                   |
| Monorepo           | multiple apps/packages in one repo |
| ML/data notebook   | Jupyter, data pipelines            |
| Infrastructure/IaC | Terraform, Pulumi, Ansible         |

**Architectural patterns to detect from folder names and dependencies:**
MVVM, MVC, hexagonal/ports-adapters, layered, event-driven, microservices, serverless, functional
pipeline.

---

## Chapter Catalogue

Number consecutively from `01`. Chapters not included must appear in `00-index.md` with a one-line
note.

| Slug                     | Include when…                                                                |
|--------------------------|------------------------------------------------------------------------------|
| `overview`               | Always                                                                       |
| `module-structure`       | Always                                                                       |
| `build-run-test`         | Always                                                                       |
| `data-flow`              | Backend API, mobile, desktop with data layer                                 |
| `request-lifecycle`      | Backend API / server-side rendered (use instead of or alongside `data-flow`) |
| `ui-navigation`          | Any app with a user interface                                                |
| `controllers-viewmodels` | MVC, MVVM, MVP, or any handler/controller layer                              |
| `persistence-repository` | Any project with a data store                                                |
| `external-integrations`  | HTTP clients, message brokers, third-party SDKs                              |
| `domain-models`          | Any project with non-trivial entities or DTOs                                |
| `configuration-env`      | Any project using env vars, feature flags, settings                          |
| `dependencies`           | Always                                                                       |
| `notable-patterns`       | Always                                                                       |
| `glossary`               | Always (especially when reader is a language neophyte)                       |

---

## Templates

### Per-chapter file

```markdown
# NN — Chapter Title

> See also: [MM — Other Chapter](MM-other-slug.md), …

One paragraph introducing what this chapter covers.

## Section heading

…body…
```

### 00-index.md

```markdown
# <Project Name> — Technical Documentation

Short description of what this documentation covers and who it is for.

## Table of Contents

| #  | Chapter | Contents |
|----|---------|----------|
| 00 | [Index](00-index.md) | This file |
| 01 | [Overview](01-overview.md) | … |

## How to Read This Book

- Chapters can be read in order; each includes cross-references.
- All paths are relative to the repository root.
- Language-specific terms are explained inline on first use and collected in the Glossary.

## Notation Conventions

- `Code` → identifiers, file names, commands.
- *italics* → domain concepts.
- → / ⇆ → data flow direction (unidirectional / bidirectional).
```

### Glossary chapter

```markdown
# NN — Glossary

> See also: all chapters.

Terms specific to the language or framework used in this codebase, explained for a developer
who has not worked with it before.

| Term | Plain-language meaning |
|------|------------------------|
| `StateFlow` | A stream that always holds the latest value and notifies observers when it changes — similar to an Observable/Subject in RxJS or a BehaviorSubject. |
```

---

## Style Rules

- **Tables** for structured lists: file → purpose, parameter → value, dependency → role.
- **Mermaid diagrams** (`flowchart LR`, `sequenceDiagram`) for data flows, request lifecycles,
  component interactions. Use ASCII art only as fallback for simple stack diagrams.
- **Code snippets** must include a comment with the relative path and line range:
  ```kotlin
  // app/src/main/java/com/example/Foo.kt, lines 40-52
  val result = repository.getAll()
  ```
  Read the actual file first — never invent line numbers.
- **Relative paths only** throughout all docs (relative to the repository root).
- **Cross-references** use relative Markdown links: `[NN — Title](NN-slug.md)`.
- *Italic* for domain concepts; `code` for identifiers, file names, commands.
- **Inline gloss** on first use of language-specific terms: `term (≈ plain-language analogy)`.