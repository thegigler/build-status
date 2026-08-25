# Build Status

A small, stateless build-artifact status dashboard.

The core operational question remains intentionally simple:

> Did the expected artifact show up, and when?

The application does not require a database, JavaScript framework, background worker, scheduler, or monitoring stack.

## Requirements

- PHP 8.1+
- read access to the build output files being monitored

## Run

```bash
php -S 127.0.0.1:8080 -t public
```

Open `http://127.0.0.1:8080/`.

Machine-readable status is available at `http://127.0.0.1:8080/status.php`.

## Configure

Edit `config/builds.json`.

A group defines artifact paths, a default schedule/rule, and optional filename-matching overrides.

Daily schedule:

```json
{
  "schedule": {
    "type": "daily",
    "times": ["00:00", "04:00", "12:00", "17:00"]
  },
  "retentionDays": 3
}
```

Weekly schedule:

```json
{
  "schedule": {
    "type": "weekly",
    "weekday": "Sunday",
    "time": "03:00"
  },
  "retentionDays": 10
}
```

Filename-specific override:

```json
{
  "match": "prebuild_manifest",
  "scheduleOffsetMinutes": -120
}
```

## Status model

- `healthy`: artifact was produced after the current scheduled start
- `running`: current build is still within the previous observed build duration
- `late`: current build has exceeded the previous observed duration
- `very-late`: current build has exceeded the prior duration plus the grace period
- `stale`: artifact is older than the retention horizon
- `missing`: artifact does not exist
- `unknown`: modification time could not be read

The previous artifact completion time is used to estimate how long the current build should reasonably take, preserving the useful behavior from the original implementation.

## Tests

```bash
php tests/run.php
```

## Structure

```text
configuration
    ↓
schedule resolution
    ↓
status evaluation
    ↓
HTML / JSON rendering
```

The renderer knows nothing about individual build schedules. The status engine knows nothing about HTML.
