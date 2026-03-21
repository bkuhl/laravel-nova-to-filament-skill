# laravel-nova-to-filament-skill

A Claude AI skill (system prompt) that guides an AI coding agent through a **complete, production-quality migration from Laravel Nova to Filament PHP v5**.

---

## Purpose

Migrating an admin panel from Laravel Nova to Filament PHP is a non-trivial undertaking.  
Resources, fields, actions, filters, lenses, metrics, dashboards, custom tools, and authorization rules all need to be understood and translated — some map 1:1, others require a plugin, and a few require a fundamentally different architectural approach.

This skill gives a Claude agent the detailed knowledge it needs to:

1. **Audit** an existing Nova application and produce a structured inventory of every artifact (resources, actions, filters, lenses, metrics, dashboards, custom tools, policies).
2. **Classify** each artifact by migration complexity (direct mapping, plugin, or custom Livewire work) and identify any non-standard components that need clarification from the developer before migration can proceed.
3. **Plan** the migration in dependency order, while running Filament at a separate URL path (`/filament`) so Nova stays live and the developer can compare both panels side-by-side throughout the process.
4. **Execute** the migration phase by phase — resources, actions, filters, lenses, metrics, dashboards, custom tools, authorization, and navigation — using idiomatic Filament v5 patterns.
5. **Verify** the migration with a per-resource acceptance checklist, Filament test helpers, and a side-by-side browser comparison workflow.
6. **Cut over** cleanly by switching paths, removing Nova, and redirecting the old URL.

---

## What the skill covers

| Topic | Detail |
|---|---|
| **Field mapping** | Every Nova field class mapped to its Filament equivalent (form + table), including edge cases like `MorphTo`, `Stack`, `resolveUsing`, `displayUsing`, `fillUsing`, and conditional visibility via `dependsOn` / `->live()` |
| **Resource settings** | `$title`, `$search`, `$group`, `$icon`, `$priority`, `$label`, `$perPageOptions`, `$clickAction`, `$polling` |
| **Form layout** | Nova `Panel` → Filament `Section` / `Tabs` / `Wizard` / `Grid` / `Fieldset` / `Split` |
| **Relationships** | `BelongsTo`, `BelongsToMany`, `HasMany`, `HasOne`, `MorphMany`, `MorphTo` → `Select`, `RelationManager` |
| **Actions** | Basic, destructive, queued, confirmation modal, action form fields, file downloads, bulk actions, header actions |
| **Filters** | `SelectFilter`, `TernaryFilter`, custom date-range filters |
| **Lenses** | Converted to custom Filament pages with embedded `InteractsWithTable` |
| **Metrics** | `Value` → `StatsOverviewWidget`, `Trend` → `ChartWidget` (line), `Partition` → `ChartWidget` (doughnut) |
| **Dashboards** | Nova dashboard pages → Filament Dashboard with registered widgets |
| **Custom tools** | Decision tree: data viz → widgets, form workflow → wizard, JS-heavy → Livewire page or iframe |
| **Authorization** | Policy method mapping, field-level `->visible()`, action `->authorize()`, resource `canCreate/canEdit/canDelete`, spatie-permission plugin |
| **Navigation** | `$group`, `$priority`, `$icon`, `Nova::mainMenu()` → `navigationItems`, `Nova::userMenu()` → `userMenuItems` |
| **Notifications** | Flash → `Notification::make()->send()`, persistent → `sendToDatabase()` |
| **Testing** | Filament Livewire test helpers, side-by-side acceptance checklist |
| **Plugins** | Curated table of community plugins for capabilities without a built-in Filament equivalent |
| **Artisan commands** | Nova vs Filament command reference |

---

## Installation

### Claude Code (recommended)

Register this repository as a Claude Code plugin marketplace and install the skill directly:

```bash
/plugin marketplace add bkuhl/laravel-nova-to-filament-skill
```

Then install the skill:

```bash
/plugin install laravel-nova-to-filament@laravel-nova-to-filament-skill
```

Once installed, mention the skill in your Claude Code session and the agent will follow the migration workflow automatically.

### Claude.ai

Upload [`SKILL.md`](./SKILL.md) as a custom skill in Claude.ai by following the [Using skills in Claude](https://support.claude.com/en/articles/12512180-using-skills-in-claude#h_a4222fa77b) instructions.

### Laravel project (Composer)

Install the package into your Laravel project via Composer:

```bash
composer require --dev bkuhl/laravel-nova-to-filament-skill
```

Then publish `SKILL.md` to your project root:

```bash
php artisan vendor:publish --tag=nova-to-filament-skill
```

This copies `SKILL.md` into the root of your Laravel project, making it ready to use with your AI coding agent.

---

## How to use this skill

1. After installing (via any method above), the agent will have access to the skill's migration workflow.
2. Point the agent at your Laravel project.
3. The agent will walk through Phases 1–14 in `SKILL.md`, asking clarifying questions where needed, and producing idiomatic Filament v5 code.

---

## File structure

```
SKILL.md             ← The skill itself: YAML frontmatter + full agent instructions,
                       component mapping, migration workflow, testing checklist, and
                       plugin reference. Compatible with Anthropic's skills format.
README.md            ← This file: describes the skill's purpose and how to install it.
.claude-plugin/      ← Claude Code plugin manifest for marketplace installation.
src/                 ← Laravel ServiceProvider that publishes SKILL.md via artisan.
composer.json        ← Package definition for Composer / Packagist.
```
