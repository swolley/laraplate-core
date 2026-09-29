# Setting actions and command-managed choices: developer and operator guide

## Columns

`core_settings` carries two code-owned columns:

| Column | Type | Meaning |
|---|---|---|
| `action_command` | nullable string | Artisan command line run from the settings grid, with `{attribute}` placeholders |
| `action_queued` | boolean, default `false` | `false` runs the command inside the request, `true` queues it |

Both are outside `Setting::$fillable`, so neither the Filament form nor the CRUD API can write them, and
inside `$hidden`, so API responses never expose them (public settings are readable by guests). Filament
fills forms through `attributesToArray()`, which honours `$hidden`: the edit form reads the two fields
from the record in `afterStateHydrated()` and shows them read-only, only when the setting has an action.

## Declaring an action

A seeder row declares it next to the usual keys:

```php
[
    ...self::setting('features.chat.model', 'ollama:llama3.2:3b', SettingTypeEnum::String, 'ai', 'AI model used by chat', ['ollama:llama3.2:3b']),
    'action_command' => 'ai:models:refresh --setting={name}',
    'action_queued' => false,
]
```

`Seeder::internalSettingsDefinition()` realigns `action_command` and `action_queued` on every seed, like
`type` and `description`, so a changed command reaches existing installations. Rows that declare no action
get `null` / `false`: an upsert needs every row to carry the same columns, and a row whose action was
removed is realigned to none.

## Running an action

`Modules\Core\Services\SettingActionRunner`:

1. Reads `action_command`; a setting without one is refused.
2. Replaces every `{attribute}` with that attribute of the setting. Scalars become strings, booleans
   `true`/`false`, `null` an empty string, arrays and objects JSON. Each value is wrapped in double quotes
   with `"` and `\` escaped: Symfony's `StringInput` reads it as one token, also in the
   `--option={name}` form, so a value holding spaces or a leading `--` cannot add arguments or options.
3. Refuses, before anything runs, with `InvalidSettingActionException`:
   - an unknown placeholder;
   - `{value}` on an encrypted setting (other placeholders of an encrypted setting are fine);
   - a command that is not registered in Artisan.
4. Runs `Artisan::call($line)` (returning exit code and output in `SettingActionResult`) or
   `Artisan::queue($line)` when `action_queued` is set.

A synchronous run happens inside the web request, with the logged-in user: whatever the command writes
follows that user's approval rules. A queued run happens in the console, where approvals do not apply.
Commands come only from seeders, so this is deliberate.

The grid action (`runSettingAction` in `SettingsTable`) is visible when `action_command` is set and the
user holds `{connection}.core_settings.update`. It shows the last 1000 characters of the output.

## Command-managed choices

`Seeder::commandManagedChoicesSettingsDefinition()` is the variant for settings whose choices a command
refreshes: it realigns the same columns as `internalSettingsDefinition()` except `choices`. The seeder's
choices are written when the row is created (use the initial value, so the select works at once) and a
re-seed never overwrites what the command wrote. `SettingsCleaner` selects rows by module state, so a
module may split its rows across both definitions.

`choices` is exempt from approval in `Setting::requiresApprovalWhen()`: users cannot edit it, and the
command writing it runs synchronously from the grid, where it would otherwise be captured.

`Setting::isValueOutsideChoices()` is true for a scalar value missing from a non-empty choice list. List
values (checkbox settings) are never flagged. The grid marks such a value, and the form keeps it
selectable as "(no longer available)" instead of blanking the select.

## Settings in queue workers

Web requests apply the database settings to config through the `ApplyDatabaseSettingsOverlay` middleware.
Console commands apply them once at boot. Queue workers used to stop there, so a setting changed in
Filament reached queued jobs only after a worker restart. `ApplySettingsOverlayBeforeJob` listens to
`JobProcessing` and re-applies the overlay before every job: the worker drops scoped instances before each
job, so it reads a fresh `PerModelSettingResolver` backed by the persistent cache the saving process
invalidated.

Consequence for tests: with `QUEUE_CONNECTION=sync`, every dispatched job re-applies the settings rows
that exist in the test database, overwriting a `config()->set()` on the same keys.

If `bootstrap/cache/events.php` exists (`php artisan event:cache`), it replaces every
`EventServiceProvider::$listen`: after adding a listener, run `php artisan event:clear` or re-cache.
