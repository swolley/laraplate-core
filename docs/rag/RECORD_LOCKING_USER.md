---
module: core
audience: user
cross_cutting_user: true
---
# Record locking — user and API guide

## What a lock is for

A lock says who may change a record right now. It exists so that two people do not edit the same
thing at the same time and quietly overwrite each other, and so that some records can be closed to
editing altogether.

There are two kinds, and they look different on screen.

| Icon | Kind | Who may edit | How long |
|------|------|--------------|----------|
| Padlock | **Lease** or **hold** | only the person named on it | a lease expires, a hold does not |
| Snowflake | **Freeze** | nobody | until its deadline, or forever |

The icon tells you **who**, not for how long. The deadline is written next to it as text. "Frozen"
therefore means *nobody owns it*, not *forever*: a freeze can perfectly well expire.

In the superadmin panel the snowflake is drawn as a crossed-out circle, because the icon set used
there has no snowflake.

## The three acts

**Taking a lease** is what happens by itself when you open a record to edit it. You do not ask for
it and there is no button: opening the edit form takes the lease, and holding it means nobody else
can save that record while you work. It lasts fifteen minutes by default.

**Holding** is a lease that never expires. It is a deliberate act and needs the `lock` permission.

**Freezing** closes a record to everybody, including you. It is what you use for records that must
stop changing: a confirmed order, a published document under legal review. It also needs `lock`.

## What you will see

| Situation | What happens |
|-----------|--------------|
| You open a record nobody holds | It opens normally and the lease is yours |
| You reopen a record you already hold | It opens normally; nothing changes |
| Somebody else holds it | You are asked to choose: go back to the list, or open read-only |
| It is frozen | Same choice, and the message says it is frozen rather than naming a person |
| You try to save a record somebody else holds | The save is refused and nothing is written |

Read-only really is read-only: the fields are disabled. That is deliberate. Letting you type into a
form whose save will be refused would waste your work.

## Expiry

A lease expires on its own. The moment it does, the record is free to everybody, whether or not the
system has got round to tidying the record up. There is no background process you have to wait for.

If your edit form is still open when the lease expires, it simply takes a new one. If somebody else
took the record in the meantime, you are told, and the answer carries the record's current version
so you can see that it changed.

Nothing extends a lease while you type. The deadline is set when the lease is taken and stays there.
This is on purpose: a record you deliberately held until Thursday must not be silently shortened to
fifteen minutes because you reopened the form.

## Releasing

Releasing a lock you hold is always yours to do and needs no permission.

Releasing **somebody else's** lock, or lifting a freeze, needs the `unlock` permission. That is
deliberately a different permission from `lock`: being trusted to close a record and being trusted
to unblock a colleague are different responsibilities, and often different people.

Releasing a record that is not locked is not an error. Nothing happens and nothing is reported as
having changed.

## API

Both verbs are `PATCH` on `/app/crud/{lock|unlock}/{module}/{entity}`, with the record id in the
body.

| Field | Meaning |
|-------|---------|
| `id` | the record. Without it the call works over the request's criteria, in bulk |
| `freeze` | `true` asks for a freeze rather than a lease. Needs `lock` |
| `locked_until` | an explicit deadline. Sent as `null` it means "no deadline", which needs `lock` |

Answers:

| Status | Meaning |
|--------|---------|
| 200, `data` empty | nothing was written: the lock is already exactly what you asked for |
| 200, `data` with the record | the lock changed, and the record carries its current `lock_version` |
| 423 | somebody else holds it, or it is frozen. The body names the holder and the deadline |
| 403 | this class of record is configured so that nobody may unlock it |
| 401 | you do not hold the permission the act requires |

There is no heartbeat call. Call `lock` again periodically and read the answer: an empty `data`
means your lock is still yours and still valid, and in that case not a single column is written.

A save can also come back **409**, which means somebody changed the record between the moment you
read it and the moment you saved. Reload and try again.
