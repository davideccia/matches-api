---
name: laravel-scaffold
description: Scaffold complete Laravel artifacts from a DBML schema or table definitions. For each table, spawns a parallel sub-agent that generates: migration, Eloquent model, observer, global scope, REST API controller, form requests (StoreRequest + UpdateRequest extending StoreRequest), and seeder (no factory). Use when the user provides a DBML file, table list, or schema and wants to generate boilerplate Laravel files for one or more models.
---

# Laravel Scaffold

Generates the full Laravel artifact set for every table in a given schema, using one parallel sub-agent per table.

## Input formats accepted

- DBML (`Table "users" { id integer [pk] ... }`)
- Plain prose (`users: id, name, email, created_at`)
- Array of table + column descriptions

## Orchestration workflow

1. **Parse** — identify every table and its columns (name, type, nullable, default, FK).
2. **Spawn one sub-agent per table in parallel** — each sub-agent receives:
   - The table name (snake_case) and model name (PascalCase)
   - The full column list with types
   - The instructions in `REFERENCE.md`
3. **Each sub-agent creates** (in this order):
   1. Migration → `database/migrations/{timestamp}_create_{table}_table.php`
   2. Model → `app/Models/{Model}.php`
   3. Observer → `app/Observers/{Model}Observer.php`
   4. Global Scope → `app/Models/Scopes/{Model}Scope.php`
   5. API Resource → `app/Http/Resources/{Model}Resource.php`
   6. Controller → `app/Http/Controllers/Api/{Model}Controller.php`
   7. IndexRequest → `app/Http/Requests/{Model}/{Model}IndexRequest.php`
   8. StoreRequest → `app/Http/Requests/{Model}/{Model}StoreRequest.php`
   9. ShowRequest → `app/Http/Requests/{Model}/{Model}ShowRequest.php`
   10. UpdateRequest → `app/Http/Requests/{Model}/{Model}UpdateRequest.php` *(extends StoreRequest)*
   11. DestroyRequest → `app/Http/Requests/{Model}/{Model}DestroyRequest.php`
   12. Seeder → `database/seeders/{Model}Seeder.php` *(no factory)*
4. **After all agents complete**, remind the user to:
   - Register the observer in a `ServiceProvider`
   - Register the global scope in the model's `booted()` if not auto-discovered
   - Add the seeder to `DatabaseSeeder`
   - Add routes in `routes/api.admin.php`: `Route::apiResource('{table}', {Model}Controller::class)`

## Sub-agent prompt template

> You are a Laravel developer. Your job is to generate ALL scaffold files for the `{Model}` model (table: `{table}`).
>
> Columns: `{column_list}`
>
> Follow every template and convention in `REFERENCE.md` exactly. Write each file using the Write tool. Do not skip any file. Do not generate a factory.

## Conventions enforced

- **All `id` columns and any `*_id` FK columns are UUIDs** — use `$table->uuid('id')->primary()` and `$table->foreignUuid('{col}_id')->constrained()->cascadeOnDelete()` in migrations; add `use Illuminate\Database\Eloquent\Concerns\HasUuids` trait to every model (replaces manual `$keyType`/`$incrementing`); cast FK columns as `'string'`
- `$fillable` lists every non-auto column
- `$casts` maps dates → `'datetime'`, booleans → `'boolean'`, JSON → `'array'`, enums → the PHP-backed enum class
- **Enum columns are always `string` in the migration** (`$table->string('col')`); a PHP string-backed enum must be created at `app/Enums/{EnumName}.php` and cast via `'col' => {EnumName}::class` in the model
- UpdateRequest **extends** StoreRequest and overrides only what changes (usually `sometimes` rules)
- **Index, Store, and Show requests** use the `InjectWith` trait (`use App\Traits\InjectWith`) and call `$this->injectWith()` in `prepareForValidation()` — this normalises the `with` query param from a comma-separated string to a camelCase array
- **`with.*` validation** always uses `Rule::in([...camelCase relationships...])` instead of `['string']` — explicitly whitelist every eager-loadable relationship; leave the array empty if none are allowed
- **IndexRequest** always includes a `'search' => ['nullable', 'string']` rule
- **DestroyRequest** does not use the `InjectWith` trait
- Controller returns `{Model}Resource` / `{Model}Resource::collection()`; uses `fill()->saveOrFail()`; supports `with` eager loading and `paginate`/`per_page`/`page` on index
- Each controller action has its own dedicated Request class (Index, Store, Show, Update, Destroy)
- Seeder uses `DB::table()->insert()` or `{Model}::create()` with static fixture data; use `Str::uuid()` for UUID primary keys
- Observer stubs all six events: `creating`, `created`, `updating`, `updated`, `deleting`, `deleted`
- Global scope adds a default `orderBy('id', 'desc')` and is applied in `booted()`
- **All CRUD routes are registered exclusively in `routes/api.admin.php`** — never in `routes/api.php`

See [REFERENCE.md](REFERENCE.md) for exact file templates.