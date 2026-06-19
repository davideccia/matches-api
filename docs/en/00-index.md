# Matches API — Technical Documentation

A guided tour of the **Matches API**, a Laravel 13 REST backend for managing combat-sports
tournaments: athletes, registrations, fight cards, and live match tracking.

This documentation is written for a developer who is **competent in general programming but new to
both PHP/Laravel and the combat-sports tournament domain**. Framework and domain terms are explained
inline on first use (with a plain-language analogy) and collected in the [Glossary](12-glossary.md).

## Table of Contents

| #  | Chapter | Contents |
|----|---------|----------|
| 00 | [Index](00-index.md) | This file |
| 01 | [Overview](01-overview.md) | What the system does, the domain, the tech stack, the two route surfaces |
| 02 | [Module Structure](02-module-structure.md) | Tour of `app/` — every layer and what lives where |
| 03 | [Build, Run & Test](03-build-run-test.md) | Sail/Docker setup, migrations, the test suite, WebSockets + queue + Horizon |
| 04 | [Request Lifecycle](04-request-lifecycle.md) | An HTTP request end-to-end, with a sequence diagram |
| 05 | [Controllers & Requests](05-controllers-and-requests.md) | Controller patterns, the 5-FormRequest convention, `InjectWith`, pagination |
| 06 | [Domain Models](06-domain-models.md) | The entities, enums, and relationships, with an ER diagram |
| 07 | [Persistence & Model Lifecycle](07-persistence-and-lifecycle.md) | UUID keys, migrations, Observers, Global Scopes |
| 08 | [Real-time, Files & PDF](08-realtime-files-and-pdf.md) | Reverb broadcasting, Media Library / S3, PDF generation |
| 09 | [Configuration & Environment](09-configuration-env.md) | Env vars, drivers, locale, throttling, Horizon auth |
| 10 | [Notable Patterns](10-notable-patterns.md) | The reusable idioms that define this codebase |
| 11 | [Dependencies](11-dependencies.md) | Each Composer package and the role it plays |
| 12 | [Glossary](12-glossary.md) | Laravel/PHP and domain terms in plain language |

### Chapters not included

- **`ui-navigation`** — not applicable. This is an API-only backend; it serves JSON and has no
  user-facing screens. The only Blade (≈ HTML template) views that exist are server-rendered PDFs
  and one transactional email, covered in [Chapter 08](08-realtime-files-and-pdf.md).

## How to Read This Book

- Chapters can be read in order; each includes cross-references at the top.
- All paths are relative to the repository root.
- Language-specific terms are explained inline on first use and collected in the Glossary.
- Project-wide conventions also live in [`CLAUDE.md`](../../CLAUDE.md) and [`README.md`](../../README.md);
  this book links to them rather than repeating them.

## Notation Conventions

- `Code` → identifiers, file names, commands.
- *italics* → domain concepts.
- → / ⇆ → data flow direction (unidirectional / bidirectional).
- `term (≈ analogy)` → a glossed framework/domain term on first use.
