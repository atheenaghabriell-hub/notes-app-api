# Notes App PHP API

## Local setup

Place this folder under XAMPP's `htdocs` directory and configure `db.php` with
the MySQL database connection. The Android emulator reaches the API at:

```text
http://10.0.2.2/notes_app/
```

The database must provide `users` and `notes` tables. The `notes` table fields
used by the API are `id`, `user_id`, `title`, `content`, `create_at`, `category`,
and `status`.

## Notes endpoints

All note mutations use `POST` form fields and return JSON with a `status`
(`success` or `error`):

- `create_note.php`: creates a note. Requires `user_id`, `title`, and `content`;
  accepts optional `category`, `status`, and `client_id`.
- `sync_notes.php`: upserts a JSON array of notes in one transaction. Each note
  requires `user_id`, `title`, `content`, and either a server `id` or stable
  `client_id`/`unique_id`. A non-numeric `id` (for example `local:...`) is
  treated as the client ID. Optional fields are `category`, `status`, and
  `created_at`. Successful responses include each input ID and its server
  `note_id`; invalid items fail the whole batch without applying partial updates.
- `edit_note.php`: updates `note_id`, `title`, `content`, and `category`.
- `archive_note.php` / `restore_note.php`: change a note's status.
- `delete_note_permanent.php`: permanently deletes a note.
- `fetch_notes.php`: `GET` with `user_id` and optional `status` (`active` by default).

## Offline sync

The Android client stores changes in SQLite first and sends pending changes when
network access returns. For offline-created notes it sends a stable `client_id`
to `create_note.php`; the endpoint creates the `note_sync_map` table on demand
and uses it to safely retry a create without duplicating the MySQL note.

The MySQL account must have permission to create `note_sync_map`. The table
records the Android client ID and corresponding server note ID. Notes created by
older clients without `client_id` continue to use the normal insert path.

Example request body for `POST /sync_notes.php`:

```json
[
  {
    "id": "local:550e8400-e29b-41d4-a716-446655440000",
    "user_id": 1,
    "title": "Offline note",
    "content": "Saved without a connection",
    "category": "Personal",
    "status": "active",
    "created_at": "2026-11-08 16:00:00"
  }
]
```
