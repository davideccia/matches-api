# 12 — Glossary

> See also: all chapters.

Terms specific to PHP/Laravel and to the combat-sports tournament domain, explained for a developer who
has worked with neither before.

## PHP / Laravel terms

| Term | Plain-language meaning |
|------|------------------------|
| **Laravel** | The web framework this app is built on — provides routing, ORM, validation, queues, broadcasting. Comparable to Spring (Java), Rails (Ruby), or NestJS (Node). |
| **Artisan** | Laravel's command-line tool (`php artisan ...`). Used to run migrations, list routes, run tests, generate files. Like `manage.py` in Django or `rails` in Rails. |
| **Composer** | PHP's dependency manager. `composer.json` is the manifest, `composer.lock` pins versions. Like npm (Node) or pip (Python). |
| **Sail** | Laravel's Docker-based local dev environment. All commands are prefixed `vendor/bin/sail`. |
| **Eloquent** | Laravel's ORM (object-relational mapper): each DB table is a PHP class, each row an object. `Model::find()`, `$model->save()`, `hasMany()`. |
| **Migration** | A versioned script that builds/alters the database schema, with `up()` and `down()` methods. Run in filename-timestamp order. |
| **Model** | An Eloquent class representing one table; declares relationships, casts, and scopes. |
| **Cast** | A declaration that converts a raw DB value to a PHP type (`'date'`, `'boolean'`, an enum class, `'array'` for JSON) and back. |
| **Relationship** | A method (`hasMany`, `belongsTo`, `morphOne`) describing a foreign-key link between models, letting you traverse `$tournament->registrations`. |
| **Eager loading** | Pre-fetching related records in one query to avoid the **N+1 problem** (one query per row). Done via `with()` / `loadMissing()`. |
| **N+1 problem** | A performance bug where looping over N records triggers N extra queries; solved by eager loading. |
| **Controller** | The class whose methods handle HTTP requests for a resource (`index`, `store`, `show`, `update`, `destroy`). |
| **`apiResource`** | A route helper mapping the five REST verbs to those five controller methods in one line. |
| **FormRequest** | A class that validates and authorizes one request *before* the controller runs. Holds `authorize()` and `rules()`. |
| **Validation rules** | Declarative constraints on input (`required`, `uuid`, `exists:table,col`, `Rule::in([...])`). Failure → automatic `422` JSON error. |
| **API Resource** | An output serializer turning a model into its JSON shape. The mandated response layer here. |
| **Middleware** | A filter wrapping the request/response (auth, locale, throttling). Like Express middleware or servlet filters. |
| **Route-model binding** | Automatic lookup that turns a URL segment (`{tournament}`, or `{athlete:tax_number}`) into a loaded model before the controller runs. |
| **Observer** | A class with methods firing on a model's lifecycle events (`creating`, `updated`, `deleting`). Bound via `#[ObservedBy(...)]`. |
| **Global scope** | A constraint auto-added to *every* query for a model (a default `WHERE`). Bound via `#[ScopedBy(...)]`; bypassed with `withoutGlobalScopes()`. |
| **Named scope** | A reusable query fragment defined with `#[Scope]` that you opt into (`->search($q)`, `->unpaid(true)`). |
| **Trait** | A reusable bundle of methods mixed into a class (`use HasUuids;`). PHP's form of horizontal code reuse / mixin. |
| **Action** | A project convention: a stateless class holding one complex operation, called from observers/controllers (e.g. `ReorderMatchRecordsAction`). |
| **Event / Broadcasting** | An object dispatched when something happens; if it `ShouldBroadcast`, it is pushed to clients over WebSockets. |
| **Reverb** | Laravel's first-party WebSocket server (the backend that pushes events). |
| **Echo** | Laravel's JavaScript client library that subscribes to broadcast channels. |
| **Channel** | A named stream events broadcast on; clients subscribe. Public channels need no auth; private ones are authorised in `channels.php`. |
| **Queue / worker** | Background job processing. Queued work (including broadcasts) is run by a `queue:work` worker; managed by Horizon in production. |
| **Horizon** | A dashboard + process supervisor for Redis-backed queues. |
| **Sanctum** | Laravel's API-token auth package. Issues bearer tokens; `auth:sanctum` protects routes. |
| **Blade** | Laravel's HTML templating language. Used here only for PDF layouts and one email — no UI. |
| **Carbon** | The date/time object Laravel returns for `date`/`datetime` casts (a richer `DateTime`). |
| **Backed enum** | A PHP enum whose cases map to a scalar value (`case SCHEDULED = 'scheduled';`). Stored as that string in the DB. |
| **Attribute** | PHP's `#[...]` annotation syntax used for metadata (`#[ObservedBy]`, `#[Scope]`, `#[ScopedBy]`). |
| **Constructor property promotion** | PHP 8 shorthand declaring + assigning a property in the constructor signature (`public function __construct(public readonly MatchRecord $matchRecord)`). |
| **`__()`** | The translation helper; looks up a key in `lang/*.json` for the active locale. |
| **`abort_if()`** | Throws an HTTP exception (with status code) when a condition holds — used in delete guards. |
| **UUID** | A 128-bit random identifier used as every primary/foreign key here, instead of auto-increment integers. |

## Combat-sports domain terms

| Term | Plain-language meaning |
|------|------------------------|
| **Tournament** | A combat-sports event an organiser runs; moves through a status lifecycle. |
| **Athlete** | A competitor, identified publicly by *tax number*. |
| **Tax number** | A national fiscal identifier (e.g. Italian *codice fiscale*) used as the public lookup key for an athlete. |
| **Discipline** | The combat style / ruleset of a bout (e.g. a kickboxing format), carrying default round configuration. |
| **Weight category** | A weight bracket (with a numeric limit) athletes compete within. |
| **Registration** | An athlete's entry into a specific tournament, in a chosen discipline + weight category; tracks payment, arrival, weigh-in. |
| **Weigh-in (`weight_in`)** | The athlete's measured weight on the day, compared against the category limit. |
| **Match record / bout** | A single fight between two athletes on the card. |
| **Fight card** | The ordered list of bouts in a tournament (the `sort` column gives the order). |
| **Red corner / blue corner** | The two opposing athletes in a bout (standard combat-sports naming for the two sides). |
| **Round** | A timed segment of a bout; a bout has `rounds` rounds of `minutes_per_round` each. |
| **Judges' points** | Per-round, per-judge scores, stored as JSON in `judges_points`. |
| **End method** | How a bout concluded: decision (unanimous/split), KO, TKO, disqualification, draw, or no contest. |
| **KO / TKO** | Knockout / technical knockout — ways a bout ends before the scorecards. |
| **Winner** | The athlete declared victor; nullable (a draw or no-contest has none). |
| **Forced** | A flag on a bout indicating it was matched/created under override rather than normal pairing. |
