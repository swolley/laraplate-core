# In-app notifications — developer and operator guide

The durable channel a feature uses to reach a person inside the application: an import that
finished, approvals waiting for a reviewer, anything a module needs to surface without email.

## The model

Notifications use Laravel's `database` channel with a Core-owned table and model.

| Column | Meaning |
|--------|---------|
| `id` | UUID primary key |
| `type` | notification class name |
| `notifiable_type` / `notifiable_id` | the recipient (today always a `User`) |
| `data` | JSON payload written by the notification class |
| `module_name` | indexed scope, mirrored from `data->scope` when the row is saved |
| `read_at` | null until the recipient marks it read |

`Modules\Core\Models\Notification` owns two behaviours worth knowing:

- `saving` copies `data->scope` into the indexed `module_name` column, so a module-scoped tray
  filters in the database rather than by decoding JSON per row;
- `scopeForModule(?string $module)` is the query side of that scope.

A notification class opts in by returning `['database']` from `via()`. `ImportFinishedNotification`
does exactly that; `PendingApprovalsNotification` reads its channels from
`core.notifications.approvals.channels` (default `['mail']`), so the same producer can reach a
mailbox, the tray, or both without code changes.

## HTTP surface

Registered in `routes/notifications.php`, required from `routes/web.php` **outside** the `/crud`
group, under `auth`:

| Method | Path | Purpose |
|--------|------|---------|
| GET | `/app/notifications` | recent notifications, optionally filtered by module |
| GET | `/app/notifications/unread-count` | badge count |
| POST | `/app/notifications/read-all` | mark every notification read |
| POST | `/app/notifications/{notification}/read` | mark one read |

Every endpoint is bound to the authenticated user: a caller can only read and mutate their own
rows. There is no administrative endpoint for reading another user's tray.

Route registration order matters and is deliberate: the literal `unread-count` and `read-all`
segments are declared **before** `{notification}`, because Laravel matches in registration order
and would otherwise read them as a notification id.

## Delivery model: poll, not push

The shipped system is **poll-based**. No notification class implements `ShouldBroadcast`, there is
no `channels.php`, and the SPA tray (`NotificationBell`, mounted once in `AppShell`) asks
`unread-count` on an interval. This is a deliberate floor, not an oversight: it needs no websocket
infrastructure and degrades to "the badge updates a little later" under load.

Moving to push later does not change the producer contract. A notification class would add
`ShouldBroadcast` and the tray would subscribe; the table, the scope column and the four endpoints
stay as they are.

## Producing a notification from a module

1. Write a notification class returning `['database']` from `via()`.
2. Put `scope` in `toArray()` when the notification belongs to one module, so a module-scoped tray
   can filter it.
3. Send it to a `User` the usual Laravel way. Core needs no registration step.

Two producers ship in Core and are worth reading as references:
`Listeners/SendImportFinishedNotification` (event-driven, one recipient) and
`Services/ApprovalNotificationService::checkAndNotify()` (scheduled sweep over pending
modifications, one notification per reviewer).

## Common failure modes

| Symptom | Check |
|---------|-------|
| Tray empty though a notification was sent | `via()` must include `database`; a mail-only notification never reaches the tray |
| Notification shows in every module's tray | `data->scope` missing, so `module_name` stayed null |
| Badge count never drops | the client marks read through the POST endpoints; reading the list does not mark anything |
| 404 on `/app/notifications/unread-count` | a route registered before it is capturing the segment as `{notification}` |

## FAQ prompts for RAG

- How does a module send an in-app notification?
- Why does my notification appear in the wrong module's tray?
- Is the notification tray push or poll?
- Which endpoints back the notification bell?
- Where does the `module_name` column come from?
