# Laravel Nova → Filament PHP v5 Migration Skill

You are an expert Laravel developer specializing in migrating applications from Laravel Nova to Filament PHP v5. Your job is to guide the migration systematically, ensuring no functionality is lost and the resulting Filament application follows Filament best practices and is idiomatic and maintainable.

## State Tracking

Before beginning any work, create a `migration/` directory in the project root containing these markdown files. Update them continuously throughout the migration:

- **`migration/inventory.md`** — Full inventory of Nova artifacts discovered (resources, actions, filters, lenses, metrics, dashboards, custom tools, policies, custom fields).
- **`migration/plan.md`** — Migration plan: complexity classifications, dependency order, items that cannot be recreated, and decisions made with the user.
- **`migration/progress.md`** — Per-resource checklist. Each item is checked off only after it has been migrated AND verified.
- **`migration/auth.md`** — Authorization analysis: every policy, gate, field-level rule, action-level rule, and their Filament equivalents.

Use subagents liberally to keep context windows small. Delegate analysis and code generation to subagents, then review and integrate their output.

---

## Phase 1 — Understand the Existing Nova Application

Before writing a single line of Filament code, build a thorough understanding of the Nova codebase.

### 1.1 Inventory every Nova artifact

Scan the project for all Nova-specific files. Common locations:

```
app/Nova/                  ← Resources, Actions, Filters, Lenses, Metrics
app/Nova/Metrics/
app/Nova/Filters/
app/Nova/Lenses/
app/Nova/Actions/
app/Providers/NovaServiceProvider.php
config/nova.php
resources/views/vendor/nova/
```

Produce a structured inventory table:

| Artifact type | File | Notes |
|---|---|---|
| Resource | `app/Nova/User.php` | Uses `Nova::userResource()` customization |
| Action | `app/Nova/Actions/ExportUsers.php` | Queued, generates CSV |
| Filter | `app/Nova/Filters/UserStatus.php` | Simple select filter |
| Lens | `app/Nova/Lenses/ActiveSubscribers.php` | Custom Eloquent query + restricted columns |
| Metric | `app/Nova/Metrics/NewUsers.php` | Value metric, ranges |
| Dashboard | `app/Nova/Dashboards/Main.php` | Default dashboard |
| Custom Tool | `app/Nova/Tools/ReportingTool.php` | Inertia-based SPA tool |

### 1.2 Classify each Nova field

For every resource, classify each field. The following Nova fields have direct native Filament equivalents requiring no plugin:

| Nova field | Filament form component | Filament table column |
|---|---|---|
| `Text` | `TextInput` | `TextColumn` |
| `Textarea` | `Textarea` | `TextColumn` |
| `Number` | `TextInput::make()->numeric()` | `TextColumn` |
| `Boolean` | `Toggle` | `IconColumn::boolean()` |
| `Select` | `Select` | `SelectColumn` |
| `MultiSelect` | `Select::make()->multiple()` | `TextColumn` |
| `Date` | `DatePicker` | `TextColumn::make()->date()` |
| `DateTime` | `DateTimePicker` | `TextColumn::make()->dateTime()` |
| `Password` | `TextInput::make()->password()` | *(hidden from table)* |
| `Hidden` | `Hidden` | *(hidden from table)* |
| `Color` | `ColorPicker` | `ColorColumn` |
| `File` | `FileUpload` | `TextColumn` (URL) |
| `Image` | `FileUpload::make()->image()` | `ImageColumn` |
| `Avatar` | `FileUpload::make()->avatar()` | `ImageColumn::make()->circular()` |
| `KeyValue` | `KeyValue` | `TextColumn` (serialized) |
| `Tags` | `TagsInput` | `TextColumn` |
| `Markdown` | `MarkdownEditor` | `TextColumn::make()->markdown()` |
| `Trix` / rich text | `RichEditor` | `TextColumn::make()->html()` |
| `BelongsTo` | `Select::make()->relationship()` | `TextColumn::make('relation.column')` |
| `BelongsToMany` | `Select::make()->multiple()->relationship()` | `TextColumn` |
| `HasMany` / `HasOne` / `HasManyThrough` | `RelationManager` | `RelationManager` |
| `MorphMany` / `MorphOne` | `RelationManager` | `RelationManager` |
| `Status` / `Badge` | `Select` | `TextColumn::make()->badge()->color(fn...)` |
| `ID` | `TextInput::make()->disabled()` | `TextColumn::make('id')->sortable()` |
| `Heading` | `Section` / `Placeholder` | *(display only)* |

The following Nova fields have **no direct native Filament equivalent**. For each one found in the codebase, research the current most-popular community package that fills the gap (search Packagist and the Filament plugin directory at runtime — see "Finding Plugins at Runtime" at the end of this document) and confirm the approach with the user:

- `Slug` — no native auto-slug equivalent; research plugin options
- `Currency` — no built-in money/currency field; research plugin options
- `Code` — no built-in code editor field; research plugin options
- `JSON` — no built-in JSON editor field; research plugin options
- `MorphTo` — no native `MorphToSelect` in Filament core; research plugin options
- `Stack` / `Line` — no direct multi-line column layout; may require `->description()` composition or a plugin
- `Gravatar` — no built-in gravatar support; requires custom state transformation

### 1.3 Analyze non-standard and custom Nova components

For each non-standard item (custom fields, complex actions, lenses, custom tools), perform thorough code analysis **before asking the user anything**:

1. Read the source file thoroughly.
2. Identify all data inputs, outputs, side effects, and UI interactions.
3. Determine what native Filament features could satisfy the same need.
4. Identify any gaps where a plugin or custom Livewire component would be required.
5. Draft a proposed migration approach with the options available.
6. Present your analysis and proposed approach to the user for confirmation or clarification.

**Custom fields** (`Field` subclasses with Vue components): Trace what data the field reads and writes, how it renders, and what interactions it supports. Analyze which native Filament form component and table column most closely matches. Propose a specific approach before asking the user.

**`resolveUsing` / `displayUsing` / `fillUsing` transformations**: These are often workarounds for missing native customization elsewhere. Before mapping them to Filament transformation callbacks, evaluate whether native Filament features eliminate the need entirely. Prefer native solutions:
- Model attribute casts (`Attribute::make()`) handle computed or transformed read values; model mutators or observers handle transformation on save — prefer these over transformation callbacks
- `->money()`, `->date()`, `->boolean()`, `->badge()`, `->color()`, `->icon()`, `->prefix()`, `->suffix()`, `->description()` cover most display customizations natively
- Only use transformation callbacks (`->formatStateUsing()`, `->getStateUsing()`, `->dehydrateStateUsing()`) when no native Filament option or model-level solution exists

**`dependsOn` / conditional visibility**: These can be complex — do extra analysis on the full data flow before proposing a solution:
1. Identify every field that triggers a change and every field that reacts to it.
2. Identify what changes (visibility, available options, required state, default value).
3. Map to Filament's `->live()` + `fn (Get $get)` pattern for field-driven reactivity, or `->hidden(fn...)` / `->visible(fn...)` for static conditions.
4. For multi-field dependency chains, consider browser MCP testing: fill the triggering field and assert the dependent field's visibility or options via DOM inspection.

**All Filament closures must be strongly typed** — use typed parameters and return types so that errors surface immediately with clear messages rather than failing silently:

```php
// Correct — strongly typed:
->hidden(fn (string $state): bool => $state === 'draft')
->color(fn (string $state): string => match ($state) { 'active' => 'success', default => 'gray' })
->options(fn (Get $get): array => Category::where('type', $get('type'))->pluck('name', 'id')->toArray())

// Avoid — untyped:
->hidden(fn ($state) => $state === 'draft')
```

**Lenses**: Trace the full Eloquent query and all custom columns, filters, and actions. These are among the most complex artifacts to migrate. Understand the business question the lens answers before planning the Filament equivalent.

**Nova cards on resource show pages**: Nova allows metric widgets at the top of a resource detail page. Filament does not support this natively on the view/edit page. Document these in `migration/plan.md` under "Features that cannot be recreated" and confirm the approach with the user.

### 1.4 Review authorization and policies — deep analysis

Authorization is the highest-risk area of a migration. Errors here expose data to unauthorized users or lock out legitimate ones. Perform exhaustive analysis and document everything in `migration/auth.md`.

**Scan and document every authorization point:**

```
app/Policies/                          ← Standard Laravel policies
app/Nova/<Resource>.php                ← authorizeToCreate, authorizeToUpdate, canSee per field/action
app/Providers/NovaServiceProvider.php  ← Nova::auth() gate, Nova::gate()
```

For every resource, document in `migration/auth.md`:
- Policy class used (or "none — uses default")
- Which of `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` are overridden and the exact logic they contain
- Every field with `->canSee(fn ...)` — what condition is checked, which user attributes or roles are involved
- Every action with `->canRun(fn ...)` — what condition is checked
- Every filter with `->canSee(fn ...)` — what condition is checked
- Any ownership-based rules (e.g., user can only edit their own records)
- Any subscription, plan, or feature-flag-based rules

**Build an authorization test matrix**: For each resource with non-trivial authorization, list every user role that exists in the application and what each role can do (create, read, update, delete, run each action, see each field). This matrix drives the browser MCP verification in Phase 13.

**Panel-level access** (`Nova::auth()`): This maps to `canAccessPanel(Panel $panel): bool` on the `User` model. Document the exact logic so it can be replicated faithfully.

---

## Phase 2 — Plan the Migration

### 2.1 Identify functionality that cannot be recreated

Before writing any code, explicitly identify features of the Nova application that have no equivalent in Filament and document them in `migration/plan.md`. For each one, present the issue and available options to the user, and document their decision before proceeding.

Known Filament limitations compared to Nova (verify whether these are still current at migration time):
- **Resource show page widgets**: Nova supports adding metric cards to the top of a resource detail view. Filament does not have a native equivalent on the view/edit page. Options: add a stats section to the infolist, create a custom view page, or drop the feature.
- **Lens-style first-class URL views**: Nova lenses are first-class URL-addressable views with their own navigation entry. In Filament these become custom pages, which may have a different navigation experience.
- **Nova cards on any page**: Nova cards can appear on dashboard, resource index, and detail pages. In Filament, widgets on resource pages are limited; dashboard widgets are fully supported.

For any feature the user wants to preserve that cannot be natively recreated, document the agreed alternative in `migration/plan.md` before touching any code.

### 2.2 Categorize each artifact

Create a migration plan table in `migration/plan.md`:

| Artifact | Complexity | Strategy | Verified |
|---|---|---|---|
| `User` resource | Low | Direct mapping | ☐ |
| `Order` resource | Medium | Custom status badge color logic | ☐ |
| `ExportUsers` action | Medium | Queued action with notification | ☐ |
| `ActiveSubscribers` lens | High | Custom page with `InteractsWithTable` | ☐ |
| `NewUsers` metric | Low | `StatsOverviewWidget` | ☐ |
| `RevenueByMonth` metric | Medium | `ChartWidget` (line) | ☐ |
| `ReportingTool` | High | Confirm approach with user first | ☐ |

Complexity ratings:
- **Low**: Direct mapping, no custom logic.
- **Medium**: Requires adapting callbacks, closures, or queries.
- **High**: Requires a custom Livewire component, architectural change, or user decision.

### 2.3 Determine migration order — test as you go

Migrate and **fully verify each item before moving to the next**. Do not batch migrations without verification — issues discovered late are expensive to unwind. After each item passes its acceptance checklist (Phase 13), mark it verified in `migration/progress.md` before continuing.

Recommended order:
1. Install & configure Filament panel (alongside Nova at a different path).
2. Shared foundation: `User` resource and authorization (everything depends on these).
3. Lookup / reference resources with no dependencies (`Category`, `Tag`, `Country`, etc.).
4. Core business resources in dependency order (parent models before child models).
5. Resources with complex relationships (BelongsToMany, polymorphic).
6. Filters and Actions (after the resources they belong to).
7. Lenses → Custom Pages.
8. Metrics → Widgets and Dashboards.
9. Custom Tools (after confirming the approach with the user — see Phase 9).
10. Navigation and polish.
11. Full authorization verification pass.

### 2.4 Plan parallel operation and browser-based comparison

Run Filament at `/filament` while Nova remains at its configured path (usually `/nova`). Both panels share the same database.

```php
// AdminPanelProvider.php
->path('filament')
```

**Browser MCP side-by-side testing strategy**: Use a browser automation MCP (e.g., Playwright MCP) to drive comparison between the two panels without requiring manual user interaction for every check. The agent should:

1. Open `/nova` in one tab and `/filament` in another.
2. Log in to both panels using the same test credentials.
3. For each resource: navigate to the list in both panels, compare visible columns and record counts, open the same record by ID in both panels and compare all field values, verify UI controls (edit/delete/action buttons) appear or are hidden correctly per user role.
4. **Read-only operations only during comparison** — because both panels share the same database, any mutation in one panel immediately affects the other. Do not create, edit, or delete records during side-by-side comparison unless the test explicitly requires it and consequences are documented.
5. If a record must be created to test a form, use a clearly marked test fixture and clean it up afterward.

---

## Phase 3 — Install & Configure Filament

### 3.1 Install Filament v5

```bash
composer require filament/filament:"^5.0"
php artisan filament:install --panels
# Follow the prompts to name the panel (e.g., "admin")
```

This creates `app/Providers/Filament/AdminPanelProvider.php`.

### 3.2 Configure the panel

In `AdminPanelProvider.php`:

```php
->id('admin')
->path('filament')         // Keep Nova at /nova during migration
->login()
->colors(['primary' => Color::Indigo])
->discoverResources(in: app_path('Filament/Resources'), for: 'App\\Filament\\Resources')
->discoverPages(in: app_path('Filament/Pages'), for: 'App\\Filament\\Pages')
->discoverWidgets(in: app_path('Filament/Widgets'), for: 'App\\Filament\\Widgets')
->middleware([...])
->authMiddleware([Authenticate::class]);
```

### 3.3 Panel access control

Migrate Nova's `Nova::auth()` gate to Filament's `canAccessPanel` method on the `User` model. This is the Filament-preferred approach — it keeps authorization logic on the model where it belongs, rather than in a service provider.

```php
// Nova (NovaServiceProvider.php) — what you are replacing:
Nova::auth(function ($request) {
    return $request->user()?->isAdmin();
});

// Filament — add to App\Models\User:
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;

class User extends Authenticatable implements FilamentUser
{
    public function canAccessPanel(Panel $panel): bool
    {
        // Replicate the exact same logic from Nova::auth()
        return $this->isAdmin();
    }
}
```

If the Nova app has complex access logic (e.g., checking roles, email domains, or subscription status), replicate that exact logic inside `canAccessPanel`. Document the mapping in `migration/auth.md`.

---

## Phase 4 — Migrate Resources

For each Nova resource, follow this pattern.

### 4.1 Resource scaffold

```bash
php artisan make:filament-resource ModelName --generate
```

`--generate` introspects the model's columns and creates a sensible starting point.

### 4.2 Map resource-level settings

| Nova | Filament |
|---|---|
| `public static $model` | `protected static string $model` |
| `public static $title` | `protected static ?string $recordTitleAttribute` |
| `public static $search` | `protected static ?array $globallySearchableAttributes` + table `->searchable()` on columns |
| `public static $globallySearchable` | `protected static bool $shouldRegisterNavigation` + `getGlobalSearchResultTitle` |
| `public static $perPageOptions` | `protected static ?int $defaultPaginationPageOption` / `->paginated([10, 25, 50])` |
| `public static $group` | `protected static ?string $navigationGroup` |
| `public static $icon` | `protected static ?string $navigationIcon` |
| `public static $priority` | `protected static ?int $navigationSort` |
| `public static $label` | `protected static ?string $modelLabel` |
| `public static $pluralLabel` | `protected static ?string $pluralModelLabel` |
| `public static $tableStyle` | Table component configuration |
| `public static $clickAction` | `->recordAction()` or `->recordUrl()` on the table |
| `public static $polling` | `->poll()` on the table |

### 4.3 Map form fields (Nova `fields()` → Filament `form()`)

Nova organises fields into panels using `Panel`. Filament uses `Section`, `Grid`, `Fieldset`, `Tabs`, `Wizard`, and `Split`.

**Nova panels → Filament sections:**
```php
// Nova
Panel::make('Contact Information')->fields([
    Text::make('Phone'),
    Text::make('Address'),
]),

// Filament
Section::make('Contact Information')->schema([
    TextInput::make('phone'),
    TextInput::make('address'),
]),
```

**Nova tabs → Filament tabs:**
```php
// Nova
Tabs::make('Details', [
    Tab::make('Info')->fields([...]),
    Tab::make('Media')->fields([...]),
]),

// Filament
Tabs::make('Details')->tabs([
    Tab::make('Info')->schema([...]),
    Tab::make('Media')->schema([...]),
]),
```

**Conditional field visibility** — use strongly typed closures:
```php
// Static page-context rules:
TextInput::make('name')->hiddenOn('edit'),

// Typed dynamic callback:
TextInput::make('name')->hidden(fn (string $operation): bool => $operation === 'edit'),

// Reactive dependency — triggering field must be ->live():
Select::make('country')->live(),
Select::make('state')
    ->options(fn (Get $get): array => State::where('country', $get('country'))->pluck('name', 'id')->toArray()),
```

**Field value transformation** — always prefer native Filament features and model-level solutions over transformation callbacks. Check these before using `->formatStateUsing()` or `->dehydrateStateUsing()`:
- `->money()`, `->date()`, `->boolean()`, `->badge()`, `->color()`, `->icon()`, `->prefix()`, `->suffix()`, `->description()` handle most display cases natively
- Model `Attribute::make()` casts handle computed/transformed read values; model mutators or observers handle transformation on save

Only use transformation callbacks when no native option exists, and always type them:
```php
// Prefer a model Attribute cast over getStateUsing:
// User model:
// use Illuminate\Database\Eloquent\Casts\Attribute;
// protected function fullName(): Attribute {
//     return Attribute::make(get: fn (): string => $this->first_name.' '.$this->last_name);
// }
TextColumn::make('full_name'),  // reads the attribute automatically

// Only when no native option covers it:
TextColumn::make('name')->formatStateUsing(fn (string $state): string => strtoupper($state)),
TextInput::make('name')->dehydrateStateUsing(fn (string $state): string => trim($state)),
```

### 4.4 Map table columns

Nova uses the same `fields()` for forms and tables. Filament separates them into `form()` and `table()`. The `--generate` flag on `make:filament-resource` creates a starting table from the model's database columns — column names are inferred automatically, you only need to add display modifiers.

For relationship columns, use dot notation — Filament resolves these from the model automatically:
```php
TextColumn::make('user.name'),       // reads $record->user->name
TextColumn::make('category.name')->sortable(),
```

Adjust the generated table to match the Nova resource's index columns and sort/search settings.

### 4.5 Map relationships

| Nova | Filament |
|---|---|
| `BelongsTo::make('User')` form | `Select::make('user_id')->relationship('user', 'name')` |
| `BelongsTo::make('User')` table | `TextColumn::make('user.name')` |
| `BelongsToMany::make('Tags')` | `Select::make('tags')->multiple()->relationship('tags', 'name')` |
| `HasMany`, `HasOne`, `HasManyThrough`, `MorphMany`, `MorphOne` | `RelationManager` |
| `MorphTo` | No native equivalent — research current community options at runtime |

**Creating a RelationManager:**
```bash
# Arguments: ResourceClass  relationshipMethod  titleColumn
php artisan make:filament-relation-manager UserResource posts title
```

The `title` argument is **required** — it tells Filament which column to display as each related record's label in the relation table. Use the most human-readable column name (e.g., `title`, `name`, `email`).

Register on the resource:
```php
public static function getRelations(): array
{
    return [PostsRelationManager::class];
}
```

### 4.6 Map Nova's `cards()` (detail view cards) → Filament infolists

Nova's `cards()` on the detail page → Filament's `infolist()` using `Infolist` schema components:

```php
// Nova
public function cards(NovaRequest $request)
{
    return [
        new OrderSummaryCard,
    ];
}

// Filament: override infolist() on the resource
public static function infolist(Infolist $infolist): Infolist
{
    return $infolist->schema([
        Section::make('Order Summary')->schema([
            TextEntry::make('total')->money('usd'),
            TextEntry::make('status')->badge(),
        ]),
    ]);
}
```

---

## Phase 5 — Migrate Actions

### 5.1 Nova action → Filament action mapping

| Nova | Filament |
|---|---|
| `Action` (basic) | `Action` in table `->actions()` |
| `DestructiveAction` | `Action::make()->color('danger')->requiresConfirmation()` |
| `ExportAsCsvAction` (Nova) | `ExportAction` from `filament/spatie-laravel-export` or custom |
| `ImportAction` | `ImportAction` from `filament/spatie-laravel-import` |
| Queued action | `Action::make()->job(MyJob::class)` or dispatch job in `handle()` |
| Action with form fields | `Action::make()->form([...])` |
| Action file download | Return `Storage::download(...)` from `handle()` |
| Standalone action (toolbar) | `->headerActions([...])` |
| Bulk action | `->bulkActions([BulkAction::make(...)])` |

**Example — queued export action:**
```php
// Nova
class ExportUsers extends Action
{
    public function handle(ActionFields $fields, Collection $models): void
    {
        // dispatch job
        ExportUsersJob::dispatch($models->pluck('id'));
    }
    public function fields(): array { return []; }
}

// Filament
Action::make('exportUsers')
    ->label('Export Users')
    ->icon('heroicon-o-arrow-down-tray')
    ->action(function (Collection $records) {
        ExportUsersJob::dispatch($records->pluck('id'));
        Notification::make()->title('Export queued')->success()->send();
    })
```

### 5.2 Action confirmation modals

```php
// Nova: DestructiveAction with confirmation
// Filament:
Action::make('delete')
    ->requiresConfirmation()
    ->modalHeading('Delete record?')
    ->modalDescription('This cannot be undone.')
    ->color('danger')
    ->action(fn ($record) => $record->delete())
```

### 5.3 Action form fields

```php
// Nova:
public function fields(): array
{
    return [
        Select::make('reason')->options([...]),
        Textarea::make('notes'),
    ];
}

// Filament:
Action::make('reject')
    ->form([
        Select::make('reason')->options([...]),
        Textarea::make('notes'),
    ])
    ->action(function (array $data, $record) {
        $record->reject($data['reason'], $data['notes']);
    })
```

---

## Phase 6 — Migrate Filters

### 6.1 Nova filter → Filament filter mapping

| Nova filter type | Filament equivalent |
|---|---|
| `Filter` (select) | `SelectFilter` |
| `Filter` (boolean) | `TernaryFilter` or `SelectFilter` |
| `Filter` (date range) | `Filter` with `DatePicker` form components |
| `BooleanFilter` | `TernaryFilter` |
| Custom `Filter` with Eloquent scope | `Filter::make()->query(fn ($query) => ...)` |

**Example:**
```php
// Nova
class UserStatusFilter extends Filter
{
    public function apply(Builder $query, mixed $value): Builder
    {
        return $query->where('status', $value);
    }
    public function options(): array
    {
        return ['active' => 'Active', 'inactive' => 'Inactive'];
    }
}

// Filament
SelectFilter::make('status')
    ->options(['active' => 'Active', 'inactive' => 'Inactive'])
```

**Date range filter:**
```php
Filter::make('created_between')
    ->form([
        DatePicker::make('from'),
        DatePicker::make('until'),
    ])
    ->query(function (Builder $query, array $data) {
        return $query
            ->when($data['from'], fn ($q) => $q->whereDate('created_at', '>=', $data['from']))
            ->when($data['until'], fn ($q) => $q->whereDate('created_at', '<=', $data['until']));
    })
```

---

## Phase 7 — Migrate Lenses

Nova lenses are custom table views with their own query, columns, and actions. In Filament they map to **custom pages** containing a `Table` widget or a full Livewire table component.

### 7.1 Strategy

1. Create a custom Filament page: `php artisan make:filament-page ActiveSubscribersPage`
2. Embed a `Table` using the `InteractsWithTable` trait.
3. Translate the lens's `query()`, `columns()`, `filters()`, and `actions()` into the Filament table equivalents.

```php
// Nova lens
class ActiveSubscribers extends Lens
{
    public static function query(Builder $query): Builder
    {
        return $query->whereHas('subscription', fn ($q) => $q->active());
    }
    public function fields(): array { ... }
    public function filters(): array { ... }
}

// Filament custom page (simplified)
class ActiveSubscribersPage extends Page implements HasTable
{
    use InteractsWithTable;

    protected static string $view = 'filament.pages.active-subscribers';

    protected function getTableQuery(): Builder
    {
        return User::query()->whereHas('subscription', fn ($q) => $q->active());
    }

    protected function getTableColumns(): array { ... }
    protected function getTableFilters(): array { ... }
    protected function getTableActions(): array { ... }
}
```

Register the page on the panel and add a navigation item.

---

## Phase 8 — Migrate Metrics & Dashboards

### 8.1 Nova metric → Filament widget mapping

| Nova metric | Filament widget |
|---|---|
| `Value` metric | `StatsOverviewWidget` (single stat card) |
| `Trend` metric | `ChartWidget` (line chart) |
| `Partition` metric | `ChartWidget` (doughnut / bar chart) |
| `Table` metric | `TableWidget` |

### 8.2 Value metric → StatsOverviewWidget

```php
// Nova
class NewUsers extends Value
{
    public function calculate(NovaRequest $request): Result
    {
        return $this->count($request, User::class);
    }
    public function ranges(): array { return [30 => '30 Days', 60 => '60 Days', 'TODAY' => 'Today']; }
}

// Filament
class NewUsersWidget extends StatsOverviewWidget
{
    protected function getStats(): array
    {
        return [
            Stat::make('New Users (30d)', User::where('created_at', '>=', now()->subDays(30))->count())
                ->description('Last 30 days')
                ->color('success'),
        ];
    }
}
```

### 8.3 Trend metric → ChartWidget

```php
// Filament
class UserGrowthChart extends ChartWidget
{
    protected static ?string $heading = 'User Growth';

    protected function getData(): array
    {
        $data = User::selectRaw('DATE(created_at) as date, COUNT(*) as count')
            ->where('created_at', '>=', now()->subDays(30))
            ->groupBy('date')
            ->pluck('count', 'date');

        return [
            'datasets' => [['label' => 'New Users', 'data' => $data->values()]],
            'labels' => $data->keys(),
        ];
    }

    protected function getType(): string { return 'line'; }
}
```

### 8.4 Partition metric → ChartWidget (doughnut)

```php
protected function getType(): string { return 'doughnut'; }
```

### 8.5 Dashboards → Filament Dashboard page

Nova dashboards map to Filament's default Dashboard page with widgets. Register widgets on the panel:

```php
// AdminPanelProvider.php
->widgets([
    NewUsersWidget::class,
    UserGrowthChart::class,
    RevenuePartitionChart::class,
])
```

Or override the dashboard page to control widget layout:
```bash
php artisan make:filament-page Dashboard --type=dashboard
```

---

## Phase 9 — Migrate Custom Tools

Nova custom tools (full Vue/Inertia SPAs registered via `Nova::tools()`) require individual decisions before any code is written.

### 9.1 Analyze and confirm approach with the user

For each custom tool:

1. Read the tool's source thoroughly — routes, controllers, Vue components, data sources, side effects.
2. Summarize its purpose, the data it reads/writes, and every user interaction it supports.
3. Present the following options to the user and confirm their choice before proceeding:
   - **a) Rebuild natively in Filament** — as a custom page, widgets, Livewire components, or a wizard.
   - **b) Replace with a plugin** — search Packagist at runtime for current popular options.
   - **c) Drop entirely** — if the functionality is no longer needed.
   - **d) Keep as-is** — linked from the Filament navigation (e.g., served at its own URL or as an iframe).
4. Document the decision in `migration/plan.md`.

### 9.2 Native Filament rebuild (option a)

Prefer native Filament solutions — seek these before considering custom Livewire:
- Data visualizations → `ChartWidget` and `StatsOverviewWidget`
- Form-driven workflows → wizard or multi-step custom page
- Data tables with custom queries → custom page with `InteractsWithTable`
- CRUD-like interfaces → a standard Filament resource

```bash
php artisan make:filament-page ToolName
```

---

## Phase 10 — Migrate Authorization & Policies

### 10.1 Policy method mapping

Filament uses the same Laravel policies as Nova. The method names are the same:

| Policy method | Controls |
|---|---|
| `viewAny` | Can access the resource list page |
| `view` | Can view a record's detail page |
| `create` | Can access the create page / see create button |
| `update` | Can access the edit page / see edit button |
| `delete` | Can delete a record |
| `restore` | Can restore a soft-deleted record |
| `forceDelete` | Can permanently delete a record |

Filament automatically discovers and uses policies registered in `AuthServiceProvider`. No additional configuration is needed for standard CRUD policies.

Work from `migration/auth.md` built in Phase 1.4. Verify every rule is replicated before considering the migration done.

### 10.2 Field-level authorization — strongly typed closures required

```php
// Nova: ->canSee(fn ($request) => $request->user()->isAdmin())

// Filament:
TextInput::make('secret_field')
    ->visible(fn (): bool => auth()->user()->isAdmin()),
```

### 10.3 Action authorization — strongly typed closures required

```php
// Nova: ->canRun(fn ($request, $model) => ...)

// Filament:
Action::make('approve')
    ->visible(fn (Model $record): bool => auth()->user()->can('approve', $record)),
```

### 10.4 Resource-level overrides

```php
public static function canCreate(): bool
{
    return auth()->user()->hasRole('admin');
}

public static function canEdit(Model $record): bool
{
    return auth()->user()->can('update', $record);
}

public static function canDelete(Model $record): bool
{
    return auth()->user()->can('delete', $record);
}
```

### 10.5 Roles & permissions packages

If the Nova app uses a roles/permissions package (e.g., `spatie/laravel-permission`), search Packagist at runtime for the current most-popular and actively maintained Filament v5 integration for that package. Verify compatibility before installing.

### 10.6 Authorization verification pass

After all resources are migrated, perform a dedicated authorization verification pass using browser MCP with each user role from the test matrix in `migration/auth.md`:
- Verify destructive actions are hidden from unauthorized roles
- Verify restricted fields are invisible to unauthorized roles
- Verify unauthorized resource URLs return 403, not empty pages
- Verify record-level ownership rules are enforced (user cannot access another user's records)

---

## Phase 11 — Navigation

### 11.1 Nova navigation → Filament navigation

| Nova | Filament |
|---|---|
| `$group` on resource | `protected static ?string $navigationGroup` |
| `$priority` on resource | `protected static ?int $navigationSort` |
| `$icon` on resource | `protected static ?string $navigationIcon` |
| `Nova::mainMenu()` (custom nav) | `->navigationItems([...])` on panel provider |
| `Nova::userMenu()` | `->userMenuItems([...])` on panel provider |
| Custom `MenuSection` | `NavigationGroup::make(...)` |

---

## Phase 12 — Notifications & Broadcasting

### 12.1 Nova notifications → Filament notifications

| Nova | Filament |
|---|---|
| `Nova::notify()` (flash) | `Notification::make()->title(...)->success()->send()` |
| `NovaNotification` (persistent) | `DatabaseNotification` via `filament/notifications` |
| `Nova::createNotification()` (DB) | `Notification::make()->sendToDatabase(auth()->user())` |

---

## Phase 13 — Testing & Verification

Run this verification workflow **after each resource is migrated** — do not wait until the full migration is done.

### 13.1 Browser MCP side-by-side comparison

Use browser automation (Playwright MCP or equivalent) to control the browser and drive the comparison. The agent navigates both panels simultaneously without requiring manual user interaction for every step.

**Setup:**
1. Ensure both `/nova` and `/filament` are reachable in the local environment.
2. Create test user accounts for each role in the authorization test matrix from `migration/auth.md`.
3. Seed a consistent, known-state dataset for comparison (use database transactions or a dedicated seed to keep it stable).

**Automated comparison script per resource:**
1. Log in to `/nova` in one tab and `/filament` in another using the same credentials.
2. Navigate to the resource list in both panels:
   - Assert record counts match.
   - Assert column headers and visible data match (or document equivalent differences).
   - Apply the same sort in both and assert the order matches.
   - Search for the same term in both and assert result sets match.
3. Open the same record (by ID) in both panels:
   - Assert every field value is identical.
   - Assert restricted fields are visible/hidden correctly per role.
4. Check each action button for every user role:
   - Authorized roles see the action and can invoke it.
   - Unauthorized roles do not see the action.
5. Check filter behavior: apply each filter in both panels and assert the result sets match.

**Shared database caution**: Both panels read and write the same database. Use **read-only operations** for side-by-side comparison. If a test requires creating or deleting a record, wrap it in a database transaction or use a dedicated test seed and clean up afterward. Do not run destructive operations on shared production-equivalent data.

### 13.2 Automated Livewire tests

```php
it('can list users', function (): void {
    $users = User::factory()->count(5)->create();
    livewire(ListUsers::class)->assertCanSeeTableRecords($users);
});

it('can create a user', function (): void {
    livewire(CreateUser::class)
        ->fillForm(['name' => 'John', 'email' => 'john@example.com', 'password' => 'password'])
        ->call('create')
        ->assertHasNoFormErrors();
    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

it('hides restricted field from non-admin', function (): void {
    actingAs(User::factory()->create(['role' => 'viewer']));
    livewire(EditUser::class, ['record' => User::factory()->create()])
        ->assertFormFieldIsHidden('secret_field');
});

it('can filter by status', function (): void {
    $active = User::factory()->active()->create();
    $inactive = User::factory()->inactive()->create();
    livewire(ListUsers::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});
```

### 13.3 Acceptance checklist per resource

Check off each item in `migration/progress.md` before declaring a resource done:

- [ ] List page renders without errors
- [ ] All table columns display correct data
- [ ] Sorting works on all sortable columns
- [ ] Global search returns correct results
- [ ] All filters narrow results correctly
- [ ] Create form contains all required fields with correct validation
- [ ] Edit form pre-populates existing values
- [ ] All relationship fields load correct options
- [ ] Conditional field visibility behaves correctly
- [ ] All actions execute correctly (including confirmation modals and action form fields)
- [ ] Bulk actions work on multiple records
- [ ] Authorization rules enforced for every role in the test matrix
- [ ] Soft delete restore/force-delete works if applicable
- [ ] All RelationManagers display and allow editing related records
- [ ] Browser MCP side-by-side comparison passed

---

## Phase 14 — Cutover

**Do not proceed with cutover automatically.** After all resources have passed verification, prompt the user:

> "All resources have been migrated and verified. Would you like to proceed with cutover (switching Filament to the production path and removing Nova), or do you need more time for manual testing first?"

Only proceed when the user explicitly confirms they are ready.

**Cutover steps:**
1. Update the Filament panel path to the desired production URL:
   ```php
   ->path('admin')   // or whatever the user chooses
   ```
2. Remove Nova:
   - Remove `nova/nova` from `composer.json` and run `composer update`.
   - Remove `NovaServiceProvider` from `bootstrap/providers.php` (Laravel 11+) or `config/app.php`.
   - Delete `app/Nova/` and `app/Providers/NovaServiceProvider.php`.
   - Remove Nova config and assets: `config/nova.php`, `public/vendor/nova/`.
3. Redirect the old Nova URL to the new Filament URL:
   ```php
   Route::redirect('/nova', '/admin');
   ```
4. Run the full test suite.
5. Deploy.

---

## Finding Plugins at Runtime

When a Nova feature has no native Filament equivalent, do not rely on hardcoded package names. Instead, search at runtime to get current, accurate recommendations:

1. Search [Packagist](https://packagist.org) for relevant terms (e.g., "filament money field", "filament slug", "filament morph").
2. Search the [Filament plugin directory](https://filamentphp.com/plugins) for community-listed packages.
3. Evaluate candidates by: total installs, most recent release date, Filament v5 compatibility statement, and open issue count.
4. Recommend the most popular and actively maintained option.
5. Confirm with the user before installing any package.

---

## Quick Reference: Nova vs Filament Artisan Commands

| Purpose | Nova | Filament |
|---|---|---|
| Create resource | `php artisan nova:resource ModelName` | `php artisan make:filament-resource ModelName --generate` |
| Create action | `php artisan nova:action ActionName` | Define inline on the resource or as a standalone class |
| Create filter | `php artisan nova:filter FilterName` | Define inline on the resource |
| Create lens | `php artisan nova:lens LensName` | `php artisan make:filament-page LensName` |
| Create metric (Value) | `php artisan nova:value MetricName` | `php artisan make:filament-widget MetricName --stats-overview` |
| Create metric (Trend/Partition) | `php artisan nova:trend MetricName` | `php artisan make:filament-widget MetricName --chart` |
| Create dashboard | `php artisan nova:dashboard DashboardName` | `php artisan make:filament-page DashboardName --type=dashboard` |
| Create custom tool | `php artisan nova:tool VendorName/ToolName` | `php artisan make:filament-page ToolName` |
| Create relation manager | *(manual)* | `php artisan make:filament-relation-manager ResourceClass relation titleColumn` |
| Install | `php artisan nova:install` | `php artisan filament:install --panels` |
| Publish assets | `php artisan nova:publish` | `php artisan filament:assets` |
