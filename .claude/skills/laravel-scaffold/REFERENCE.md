# Laravel Scaffold — File Templates

Use these templates verbatim. Replace `{Model}`, `{model}`, `{table}`, `{fillable_array}`, `{casts_array}`, and `{validation_rules}` with the actual values derived from the column definitions.

---

## 1. Migration

**Path:** `database/migrations/{timestamp}_create_{table}_table.php`

Use `date('Y_m_d_His')` format for `{timestamp}`.

```php
<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('{table}', function (Blueprint $table) {
            $table->uuid('id')->primary();
            // {columns}
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('{table}');
    }
};
```

**Column type mapping (DBML → Blueprint):**

| DBML type         | Blueprint method                    |
|-------------------|-------------------------------------|
| `id` (primary)    | `$table->uuid('id')->primary()`     |
| `integer`         | `$table->integer('col')`            |
| `bigint`          | `$table->bigInteger('col')`         |
| `varchar`, `text` | `$table->string('col')` / `->text()`|
| `boolean`         | `$table->boolean('col')`            |
| `timestamp`       | `$table->timestamp('col')`          |
| `decimal(p,s)`    | `$table->decimal('col', p, s)`      |
| `json`            | `$table->json('col')`               |
| FK (`ref: > x.id`)| `$table->foreignUuid('{col}_id')->constrained()->cascadeOnDelete()` |

Add `->nullable()` when the column is optional. Add `->default(val)` when a default is specified.

**Enum columns** — always store as `string` in the migration, never as a DB ENUM:
```php
$table->string('status')->default('active');
```
Then create a PHP string-backed enum (see §4b) and cast it in the model.

---

## 2. Eloquent Model

**Path:** `app/Models/{Model}.php`

```php
<?php

namespace App\Models;

use App\Models\Scopes\{Model}Scope;
use App\Observers\{Model}Observer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Attributes\ScopedBy;

#[ObservedBy([{Model}Observer::class])]
#[ScopedBy([{Model}Scope::class])]
class {Model} extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = {fillable_array};

    protected function casts(): array
    {
        return {casts_array};
    }
}
```

**`$fillable`**: all non-auto columns (exclude `id`, `created_at`, `updated_at`).

**`casts()`**: include date/boolean/json columns, and cast every `*_id` FK column as `'string'`. Example:
```php
return [
    'user_id'      => 'string',
    'published_at' => 'datetime',
    'is_active'    => 'boolean',
    'metadata'     => 'array',
];
```

---

## 3. Observer

**Path:** `app/Observers/{Model}Observer.php`

```php
<?php

namespace App\Observers;

use App\Models\{Model};

class {Model}Observer
{
    public function creating({Model} ${model}): void {}

    public function created({Model} ${model}): void {}

    public function updating({Model} ${model}): void {}

    public function updated({Model} ${model}): void {}

    public function deleting({Model} ${model}): void {}

    public function deleted({Model} ${model}): void {}
}
```

---

## 4. Global Scope

**Path:** `app/Models/Scopes/{Model}Scope.php`

```php
<?php

namespace App\Models\Scopes;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Scope;

class {Model}Scope implements Scope
{
    public function apply(Builder $builder, Model $model): void
    {
        $user = auth()->user();
        
        if($user === null) {
            return;
        }
    }
}
```

---

## 4b. PHP Enum (for enum columns)

**Path:** `app/Enums/{EnumName}.php`

Create one enum per logical enum column. Name it after the domain concept, not the column (e.g. `SubscriptionStatus`, not `Status`).

```php
<?php

namespace App\Enums;

enum {EnumName}: string
{
    case Active   = 'active';
    case Inactive = 'inactive';
    // add cases matching the allowed string values
}
```

**Cast in the model** (`casts()` method):
```php
'status' => \App\Enums\{EnumName}::class,
```

**Validation rule** in StoreRequest — use `Rule::enum`:
```php
use Illuminate\Validation\Rules\Enum;

'status' => ['required', new Enum(\App\Enums\{EnumName}::class)],
```

**Never use `$table->enum()`** in migrations. Always `$table->string('col')`.

---

## 5. API Resource

**Path:** `app/Http/Resources/{Model}Resource.php`

```php
<?php

namespace App\Http\Resources;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

class {Model}Resource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $resourceArray = parent::toArray($request);
        
        //
        
        return $resourceArray;
    }
}
```

---

## 6. REST API Controller

**Path:** `app/Http/Controllers/Api/{Model}Controller.php`

```php
<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Requests\{Model}\{Model}DestroyRequest;
use App\Http\Requests\{Model}\{Model}IndexRequest;
use App\Http\Requests\{Model}\{Model}ShowRequest;
use App\Http\Requests\{Model}\{Model}StoreRequest;
use App\Http\Requests\{Model}\{Model}UpdateRequest;
use App\Http\Resources\{Model}Resource;
use App\Models\{Model};
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Resources\Json\ResourceCollection;

class {Model}Controller extends Controller
{
    public function index({Model}IndexRequest $request): ResourceCollection
    {
        $validated = $request->validated();

        ${model}s = {Model}::with($validated['with'] ?? []);

        if ($validated['paginate'] ?? false) {
            ${model}s = ${model}s->paginate($validated['limit'] ?? null);
        } else {
            ${model}s = ${model}s->get();
        }

        return {Model}Resource::collection(${model}s);
    }

    public function store({Model}StoreRequest $request): {Model}Resource
    {
        $validated = $request->validated();

        ${model} = new {Model}();
        ${model}->fill($validated)->saveOrFail();

        return new {Model}Resource(${model}->loadMissing($validated['with'] ?? []));
    }

    public function show({Model}ShowRequest $request, {Model} ${model}): {Model}Resource
    {
        $validated = $request->validated();

        return new {Model}Resource(${model}->loadMissing($validated['with'] ?? []));
    }

    public function update({Model}UpdateRequest $request, {Model} ${model}): {Model}Resource
    {
        $validated = $request->validated();

        ${model}->fill($validated)->saveOrFail();

        return new {Model}Resource(${model}->loadMissing($validated['with'] ?? []));
    }

    public function destroy({Model}DestroyRequest $request, {Model} ${model}): JsonResponse
    {
        ${model}->delete();

        return response()->json([], 204);
    }
}
```

---

## 7. IndexRequest

**Path:** `app/Http/Requests/{Model}/{Model}IndexRequest.php`

```php
<?php

namespace App\Http\Requests\{Model};

use Illuminate\Foundation\Http\FormRequest;

class {Model}IndexRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'with'     => ['nullable', 'array'],
            'with.*'   => ['string'],
            'paginate' => ['nullable', 'boolean'],
            'limit'    => ['nullable', 'integer', 'min:1'],
        ];
    }
}
```

---

## 8. StoreRequest

**Path:** `app/Http/Requests/{Model}/{Model}StoreRequest.php`

```php
<?php

namespace App\Http\Requests\{Model};

use Illuminate\Foundation\Http\FormRequest;

class {Model}StoreRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            ...{validation_rules},
            'with'   => ['nullable', 'array'],
            'with.*' => ['string'],
        ];
    }
}
```

**Validation rule mapping:**

| Column type / constraint | Rule                                              |
|--------------------------|---------------------------------------------------|
| `not null` string        | `['required', 'string', 'max:255']`               |
| nullable string          | `['nullable', 'string', 'max:255']`               |
| `not null` integer       | `['required', 'integer']`                         |
| nullable integer         | `['nullable', 'integer']`                         |
| boolean                  | `['required', 'boolean']`                         |
| email                    | `['required', 'email']`                           |
| FK `{col}_id`            | `['required', 'string', 'uuid', 'exists:{ref_table},id']` |
| json / array             | `['nullable', 'array']`                           |
| date / timestamp         | `['nullable', 'date']`                            |

---

## 9. ShowRequest

**Path:** `app/Http/Requests/{Model}/{Model}ShowRequest.php`

```php
<?php

namespace App\Http\Requests\{Model};

use Illuminate\Foundation\Http\FormRequest;

class {Model}ShowRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [
            'with'   => ['nullable', 'array'],
            'with.*' => ['string'],
        ];
    }
}
```

---

## 10. UpdateRequest

**Path:** `app/Http/Requests/{Model}/{Model}UpdateRequest.php`

```php
<?php

namespace App\Http\Requests\{Model};

class {Model}UpdateRequest extends {Model}StoreRequest {}
```

> Automatically makes all StoreRequest rules optional for PATCH-style updates. The `with` rules are inherited.

---

## 11. DestroyRequest

**Path:** `app/Http/Requests/{Model}/{Model}DestroyRequest.php`

```php
<?php

namespace App\Http\Requests\{Model};

use Illuminate\Foundation\Http\FormRequest;

class {Model}DestroyRequest extends FormRequest
{
    public function authorize(): bool
    {
        return auth()->hasUser();
    }

    public function rules(): array
    {
        return [];
    }
}
```

---

## 8. Seeder

**Path:** `database/seeders/{Model}Seeder.php`

Do **not** use a factory. Insert a small set of static fixture rows.

```php
<?php

namespace Database\Seeders;

use App\Models\{Model};
use Illuminate\Database\Seeder;

class {Model}Seeder extends Seeder
{
    public function run(): void
    {
        $rows = [
            // {example_row_1}
            // {example_row_2}
        ];

        foreach ($rows as $row) {
            {Model}::create($row);
        }
    }
}
```

Populate `$rows` with 2–3 representative fixture records using the model's fillable columns.
