# Laravel Nova → Filament PHP v5 Migration Skill

You are an expert Laravel developer specializing in migrating applications from Laravel Nova to Filament PHP v5. Your job is to guide the migration systematically, ensuring no functionality is lost and the resulting Filament application is idiomatic and maintainable.

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

### 1.2 Classify each Nova field used

For every resource, list every field and note whether it requires a plugin or custom implementation in Filament:

| Nova field class | Filament equivalent | Notes |
|---|---|---|
| `Text` | `TextInput` / `TextColumn` | Direct mapping |
| `Textarea` | `Textarea` / `TextColumn` | Direct mapping |
| `Number` | `TextInput::make()->numeric()` | Direct mapping |
| `Boolean` | `Toggle` / `IconColumn::boolean()` | Direct mapping |
| `Select` | `Select` / `SelectColumn` | Direct mapping |
| `MultiSelect` | `Select::make()->multiple()` | Direct mapping |
| `Date` | `DatePicker` / `TextColumn::date()` | Direct mapping |
| `DateTime` | `DateTimePicker` / `TextColumn::dateTime()` | Direct mapping |
| `Password` | `TextInput::make()->password()` | Direct mapping |
| `Hidden` | `Hidden` | Direct mapping |
| `Color` | `ColorPicker` / `ColorColumn` | Direct mapping |
| `Currency` | `TextInput::make()->numeric()->prefix('$')` | May need `filament/money` plugin |
| `File` | `FileUpload` / `ImageColumn` | Direct mapping |
| `Image` | `FileUpload::make()->image()` / `ImageColumn` | Direct mapping |
| `Avatar` | `FileUpload::make()->avatar()` / `ImageColumn::circular()` | Direct mapping |
| `KeyValue` | `KeyValue` | Direct mapping |
| `Tags` | `TagsInput` | Direct mapping |
| `Slug` | `TextInput` + `->live()->afterStateUpdated(...)` | Manual slug generation or `filament/spatie-laravel-sluggable` |
| `Markdown` | `MarkdownEditor` / `TextColumn->markdown()` | Direct mapping |
| `Trix` (rich text) | `RichEditor` | Direct mapping; or `filament/spatie-laravel-tiptap-editor` for advanced |
| `Code` | `Textarea` or community `CodeEditor` field | Plugin needed for syntax highlighting |
| `JSON` | `KeyValue` or custom `JsonEditor` | Community plugin may be needed |
| `BelongsTo` | `Select::make()->relationship()` | Direct mapping |
| `BelongsToMany` | `Select::make()->multiple()->relationship()` | Direct mapping |
| `HasMany` | `RelationManager` | See §3.5 |
| `HasOne` | Inline `RelationManager` or nested form via `HasOneThrough` | See §3.5 |
| `HasManyThrough` | `RelationManager` | See §3.5 |
| `MorphMany` | `RelationManager` with `->morphToMany()` | See §3.5 |
| `MorphTo` | `MorphToSelect` (community) or manual `Select` with `morphMap` | Needs careful review |
| `MorphOne` | Inline `RelationManager` | See §3.5 |
| `Status` | `SelectColumn` + color mapping or `BadgeColumn` | Use `->badge()->color(fn...)` |
| `Badge` | `TextColumn::make()->badge()` | Direct mapping |
| `Stack` | Custom `TextColumn` with `->description()` | Layout differs |
| `Line` (inside Stack) | `TextColumn->description()` | Partial mapping |
| `Gravatar` | `ImageColumn::make('email')->state(fn...)` | Manual URL generation |
| `ID` | `TextColumn::make('id')->sortable()` | Direct mapping |
| `Heading` | `Section` / `Placeholder` | Section headers, not field data |

### 1.3 Identify non-standard or custom Nova components

Ask the user to explain any item where the intent is not obvious from code alone:

- **Custom fields** (`Field` subclasses, Vue components): Clarify what data they display/collect and whether a Filament community field or a custom Livewire component can replace them.
- **Custom actions** with complex side effects: Understand what the action does, whether it is queued, whether it requires a confirmation modal, form fields, or file downloads.
- **Lenses** with heavily customised Eloquent queries: Understand the business purpose — is this a filtered table, a report, or an aggregate view?
- **Metrics**: Understand whether they display a single number (Value), a trend over time (Trend), or a proportional breakdown (Partition).
- **Custom tools** (full Inertia/Vue SPAs): These require the most discussion. Understand what the tool provides and whether it can be replaced by a Filament page, a widget, or requires embedding an external app.
- **Nova cards**: Understand whether these are informational widgets, quick-link panels, or data visualizations.
- **`resolveUsing` / `displayUsing` / `fillUsing`**: Understand the transformation logic — these become Filament's `->formatStateUsing()`, `->getStateUsing()`, or `->mutateFormDataUsing()`.
- **`dependsOn` / `hide` / `show` (conditional visibility)**: These map to Filament's `->hidden(fn...)` / `->visible(fn...)` and `->reactive()` / `->live()` system.

### 1.4 Review authorization & policies

```
app/Policies/             ← Standard Laravel policies used by Nova
app/Nova/<Resource>.php   ← authorizeToCreate, authorizeToUpdate, etc.
app/Providers/NovaServiceProvider.php  ← Nova::auth(), gate callbacks
```

Document:
- Which resources use `Policy` classes vs. inline `authorizable()` callbacks.
- Any `viewAny`, `view`, `create`, `update`, `delete`, `restore`, `forceDelete` overrides.
- Nova's `Nova::auth()` gate callback — this controls who can access the admin panel at all.
- Any resource-level `canSee` / field-level `canSee` / action-level `canRun` / filter-level `canSee` usage.

---

## Phase 2 — Plan the Migration

### 2.1 Categorize each artifact

Create a migration plan table:

| Artifact | Migration complexity | Strategy |
|---|---|---|
| `User` resource | Low — standard CRUD fields | 1:1 mapping |
| `Order` resource | Medium — custom Status field with colour logic | Map to `BadgeColumn` with colour callback |
| `ExportUsers` action | Medium — queued CSV export | `Action` with `->queue()` and file download response |
| `ActiveSubscribers` lens | High — custom query + limited columns | Custom Filament `Page` with embedded table |
| `NewUsers` metric | Low — Value metric | Filament `StatsOverviewWidget` |
| `RevenueByMonth` metric | Medium — Trend metric | `ChartWidget` (line chart) |
| `ReportingTool` | High — custom Vue SPA | Evaluate: Livewire page, iframe embed, or external link |

Complexity ratings:
- **Low**: Direct field/class mapping, no custom logic.
- **Medium**: Requires adapting callbacks, modifying queries, or using a plugin.
- **High**: Requires a custom Livewire component, a community plugin, or architectural changes.

### 2.2 Determine migration order (dependency-aware)

Migrate in this order to avoid broken references:

1. **Install & configure Filament panel** (alongside Nova, at a different URL prefix).
2. **Shared foundation**: Roles, permissions, `User` resource (because other resources reference it).
3. **Lookup / reference resources** with no dependencies (e.g., `Category`, `Tag`, `Country`).
4. **Core business resources** in dependency order (e.g., `Product` before `Order`, `Order` before `OrderItem`).
5. **Resources with complex relationships** (BelongsToMany, Polymorphic).
6. **Filters and Actions** (after the resources they belong to).
7. **Lenses → Custom Pages**.
8. **Metrics → Widgets** and **Dashboards**.
9. **Custom Tools → Custom Pages**.
10. **Authorization** (policies, panel access).
11. **Navigation** (menu items, groupings, ordering).
12. **Notifications & broadcasting** (if used).

### 2.3 Plan parallel operation

Run Filament at `/filament` (or another prefix) while Nova remains at `/nova`. This allows the user to directly compare behavior in both panels during the migration.

Filament panel configuration (`app/Providers/Filament/AdminPanelProvider.php`):

```php
->path('filament')
```

Nova remains at its configured path (`/nova` by default). Both panels read from the same database, so the user can create/edit/delete records in one and immediately verify the other.

---

## Phase 3 — Install & Configure Filament

### 3.1 Install Filament v5

```bash
composer require filament/filament:"^5.0"
php artisan filament:install --panels
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

### 3.3 Replicate Nova's panel access gate

Nova uses `Nova::auth()` in `NovaServiceProvider`. Replicate this in Filament:

```php
// Nova (NovaServiceProvider.php)
Nova::auth(function ($request) {
    return $request->user()?->isAdmin();
});

// Filament (AdminPanelProvider.php)
->authGuard('web')
->authMiddleware([Authenticate::class])
// AND in a Policy or via ->authorize():
->authorize(fn (): bool => auth()->user()?->isAdmin())
```

Alternatively, use `canAccessPanel` on the `User` model:

```php
// App\Models\User
public function canAccessPanel(Panel $panel): bool
{
    return $this->isAdmin();
}
```

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

**Conditional field visibility:**
```php
// Nova: ->hide(fn ($request) => ...) / ->showOnCreating() / ->hideFromDetail()

// Filament:
TextInput::make('name')
    ->hiddenOn('edit')           // hiddenOn / visibleOn
    ->hidden(fn () => ...)       // dynamic callback
    ->live()                     // makes field reactive so other fields can depend on it
```

**Field value transformation:**
```php
// Nova:
Text::make('Name')->displayUsing(fn ($value) => strtoupper($value)),
Text::make('Name')->resolveUsing(fn ($model) => $model->first_name . ' ' . $model->last_name),
Text::make('Name')->fillUsing(fn ($model, $value) => $model->name = trim($value)),

// Filament (table column):
TextColumn::make('name')->formatStateUsing(fn ($state) => strtoupper($state)),
// Filament (computed column):
TextColumn::make('full_name')->getStateUsing(fn ($record) => $record->first_name . ' ' . $record->last_name),
// Filament (form, on save):
TextInput::make('name')->dehydrateStateUsing(fn ($state) => trim($state)),
```

**Field dependencies / reactivity:**
```php
// Nova: ->dependsOn(['country'], fn ($field, $request, $formData) => ...)

// Filament:
Select::make('country')->live(),   // triggers re-render of dependent fields
Select::make('state')
    ->options(fn (Get $get) => State::where('country', $get('country'))->pluck('name', 'id')),
```

### 4.4 Map table columns (Nova `fields()` index view → Filament table `columns()`)

Nova uses the same `fields()` method for forms and tables (controlled by `showOnIndex`, `hideFromIndex`). Filament separates form schema from table columns.

```php
// Filament table columns
public static function table(Table $table): Table
{
    return $table->columns([
        TextColumn::make('name')->sortable()->searchable(),
        TextColumn::make('email')->sortable()->searchable(),
        TextColumn::make('status')
            ->badge()
            ->color(fn (string $state): string => match ($state) {
                'active'   => 'success',
                'inactive' => 'danger',
                default    => 'gray',
            }),
        ImageColumn::make('avatar'),
        IconColumn::make('is_admin')->boolean(),
        TextColumn::make('created_at')->dateTime()->sortable(),
    ]);
}
```

**Nova sortable/searchable → Filament:**
```php
// Nova: Text::make('Name')->sortable()->searchable()
// Filament: TextColumn::make('name')->sortable()->searchable()
```

### 4.5 Map relationships

| Nova | Filament |
|---|---|
| `BelongsTo::make('User')` (form) | `Select::make('user_id')->relationship('user', 'name')` |
| `BelongsTo::make('User')` (table) | `TextColumn::make('user.name')` |
| `BelongsToMany::make('Tags')` | `Select::make('tags')->multiple()->relationship('tags', 'name')` |
| `HasMany::make('Posts')` | `RelationManager` (see below) |
| `HasOne::make('Profile')` | `RelationManager` or inline `HasOneThrough` |
| `MorphMany::make('Comments')` | `RelationManager` with morphed relationship |
| `MorphTo::make('Subject')` | `MorphToSelect` community field or manual implementation |

**Creating a RelationManager:**
```bash
php artisan make:filament-relation-manager UserResource posts title
```

This creates `app/Filament/Resources/UserResource/RelationManagers/PostsRelationManager.php`.

Register it on the resource:
```php
public static function getRelations(): array
{
    return [
        PostsRelationManager::class,
    ];
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

Nova custom tools (full Vue/Inertia SPAs registered via `Nova::tools()`) are the hardest to migrate. Each requires individual analysis.

### 9.1 Decision tree

```
Is the tool a data visualization?
  → Yes: Use a Filament custom page with widgets and charts.
  → No: Is it a form-driven workflow?
       → Yes: Use a Filament wizard page or multi-step form.
       → No: Is it an embedded iframe or external URL?
            → Yes: Use a Filament custom page with an iframe component.
            → No: Does it require a real-time, JS-heavy UI?
                 → Yes: Consider keeping it as a standalone app linked from the Filament nav,
                         or rebuilding it as a Livewire component embedded in a Filament page.
                 → No: Rebuild as a Filament custom page with Livewire.
```

### 9.2 Creating a custom Filament page

```bash
php artisan make:filament-page ReportingPage
```

Add navigation:
```php
protected static ?string $navigationIcon = 'heroicon-o-chart-bar';
protected static ?string $navigationGroup = 'Reports';
protected static ?string $title = 'Reporting';
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

### 10.2 Field-level authorization

```php
// Nova: field->canSee(fn ($request) => $request->user()->isAdmin())

// Filament:
TextInput::make('secret_field')
    ->visible(fn () => auth()->user()->isAdmin())
    // or:
    ->hidden(fn () => ! auth()->user()->isAdmin())
```

### 10.3 Action authorization

```php
// Nova: action->canRun(fn ($request, $model) => ...)

// Filament:
Action::make('approve')
    ->visible(fn ($record) => auth()->user()->can('approve', $record))
    // or:
    ->authorize(fn ($record) => auth()->user()->can('approve', $record))
```

### 10.4 Resource-level overrides

```php
// Filament resource
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

### 10.5 Roles & permissions (spatie/laravel-permission)

If the Nova app uses `spatie/laravel-permission`, install the Filament integration:

```bash
composer require filament/spatie-laravel-permission-plugin:"^3.0"
```

This provides `SpatiePermissionPlugin` and pre-built resource pages for managing roles and permissions.

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

### 13.1 Side-by-side comparison workflow

Because Filament runs at `/filament` and Nova at `/nova`:

1. Open two browser tabs — one at `/nova`, one at `/filament`.
2. For each resource, verify:
   - **List page**: Same records appear; same columns (or equivalent); sorting and searching produce the same results.
   - **Create page**: All required fields are present; validation rules behave identically; record is saved correctly.
   - **Edit page**: Pre-populated values are correct; conditional field visibility works; saved values are correct.
   - **Detail/View page**: All fields are displayed; related records are shown.
   - **Actions**: Each action produces the same outcome.
   - **Filters**: Each filter narrows the table correctly.
   - **Authorization**: Users with different roles see the correct controls.

### 13.2 Automated testing

Filament provides test helpers via `livewire/livewire` and the `filament/filament` test suite:

```php
// Test a resource list page
it('can list users', function () {
    $users = User::factory()->count(5)->create();

    livewire(ListUsers::class)
        ->assertCanSeeTableRecords($users);
});

// Test a resource create page
it('can create a user', function () {
    livewire(CreateUser::class)
        ->fillForm(['name' => 'John', 'email' => 'john@example.com', 'password' => 'password'])
        ->call('create')
        ->assertHasNoFormErrors();

    expect(User::where('email', 'john@example.com')->exists())->toBeTrue();
});

// Test an action
it('can export users', function () {
    $user = User::factory()->create();

    livewire(ListUsers::class)
        ->callTableAction('exportUsers', $user)
        ->assertNotified();
});

// Test a filter
it('can filter by status', function () {
    $active = User::factory()->active()->create();
    $inactive = User::factory()->inactive()->create();

    livewire(ListUsers::class)
        ->filterTable('status', 'active')
        ->assertCanSeeTableRecords([$active])
        ->assertCanNotSeeTableRecords([$inactive]);
});
```

### 13.3 Acceptance checklist per resource

For every migrated resource, complete this checklist before declaring it done:

- [ ] List page renders without errors
- [ ] All table columns display correct data
- [ ] Sorting works on all sortable columns
- [ ] Global search returns correct results
- [ ] All filters narrow results correctly
- [ ] Create form contains all required fields with correct validation
- [ ] Edit form pre-populates existing values
- [ ] All relationship fields load correct options
- [ ] All conditional field visibility rules behave correctly
- [ ] All actions execute correctly (including confirmation modals and form fields)
- [ ] Bulk actions work on multiple records
- [ ] Policy/authorization rules are enforced (test with different user roles)
- [ ] Soft delete restore/force-delete works if applicable
- [ ] All RelationManagers display and allow editing related records

---

## Phase 14 — Cutover

When all resources are migrated and verified:

1. Update the Filament panel path from `/filament` to the desired production path (e.g., `/admin`).
2. Disable or remove Nova: remove `nova/nova` from `composer.json`, remove `NovaServiceProvider` from `config/app.php`, delete `app/Nova/` and `app/Providers/NovaServiceProvider.php`.
3. Redirect the old Nova URL (`/nova`) to the new Filament URL (`/admin`) via a route or web server rule.
4. Run the full test suite.
5. Deploy.

---

## Reference: Plugins to Evaluate

Some Nova capabilities do not have a 1:1 built-in Filament equivalent. Evaluate these community plugins:

| Capability | Recommended plugin |
|---|---|
| Roles & permissions UI | `filament/spatie-laravel-permission-plugin` |
| CSV import | `filament/spatie-laravel-import` or `konnco/filament-import` |
| CSV/Excel export | `filament/spatie-laravel-export` or `pxlrbt/filament-excel` |
| Rich text / Tiptap | `filament-tiptap-editor/filament-tiptap-editor` |
| Money / currency | `pelmered/filament-money-field` |
| Slug auto-generation | `filament/spatie-laravel-sluggable` |
| Media library | `filament/spatie-laravel-media-library-plugin` |
| Activity log | `filament/spatie-laravel-activitylog-plugin` or `z3d0x/filament-logger` |
| Translatable models | `filament/spatie-laravel-translatable-plugin` |
| Sortable records (drag-and-drop order) | `filament/spatie-laravel-sortable-plugin` |
| Settings management | `filament/spatie-laravel-settings-plugin` |
| Full-text search | `awcodes/filament-quick-create` (search bar) |
| Recurring tasks / scheduling UI | `filament/spatie-laravel-schedule-monitor` |
| Advanced charts | `leandrocfe/filament-apex-charts` |
| Code editor field | `guava/filament-monaco-editor` |
| MorphTo select | `filament-morph-to-select` (community) |
| Google Maps / location | `dotswan/filament-map-picker` |

---

## Quick Reference: Nova vs Filament Artisan Commands

| Purpose | Nova | Filament |
|---|---|---|
| Create resource | `php artisan nova:resource ModelName` | `php artisan make:filament-resource ModelName` |
| Create action | `php artisan nova:action ActionName` | Actions are defined inline or in separate classes |
| Create filter | `php artisan nova:filter FilterName` | Filters are defined inline on the resource |
| Create lens | `php artisan nova:lens LensName` | `php artisan make:filament-page LensName` |
| Create metric (Value) | `php artisan nova:value MetricName` | `php artisan make:filament-widget MetricName --stats-overview` |
| Create metric (Trend) | `php artisan nova:trend MetricName` | `php artisan make:filament-widget MetricName --chart` |
| Create metric (Partition) | `php artisan nova:partition MetricName` | `php artisan make:filament-widget MetricName --chart` |
| Create dashboard | `php artisan nova:dashboard DashboardName` | `php artisan make:filament-page DashboardName` |
| Create custom tool | `php artisan nova:tool VendorName/ToolName` | `php artisan make:filament-page ToolName` |
| Install | `php artisan nova:install` | `php artisan filament:install --panels` |
| Publish assets | `php artisan nova:publish` | `php artisan filament:assets` |
