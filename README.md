# ZKTeco Attendance Sync

A Laravel console application that fetches attendance logs from a **ZKTeco MB20VL** biometric attendance device and POSTs the data as JSON to a configurable API endpoint.

## Requirements

- PHP 8.2+
- `ext-sockets` enabled in `php.ini`
- Network access to the ZKTeco device (UDP port 4370 by default)
- Composer

## Setup

### 1. Install dependencies

```bash
composer install
```

### 2. Configure environment

```bash
cp .env.example .env
php artisan key:generate
```

Edit `.env` with your device and API details:

```env
APP_TIMEZONE=Asia/Dhaka   # or your local timezone

# ZKTeco device
ZKTECO_IP=192.168.1.201
ZKTECO_PORT=4370
ZKTECO_TIMEOUT=30

# Target API
API_URL=https://your-api.example.com/api/attendance
API_KEY=your-bearer-token-here
API_TIMEOUT=30
```

### 3. Enable PHP sockets

In your `php.ini`, uncomment:

```ini
extension=sockets
```

Then restart your web server / PHP process.

---

## Usage

### Basic sync (all logs)

```bash
php artisan attendance:fetch
```

### Filter by date

```bash
# Single day
php artisan attendance:fetch --date=2025-06-07

# Date range
php artisan attendance:fetch --from=2025-06-01 --to=2025-06-07
```

### Override device / API settings at runtime

```bash
php artisan attendance:fetch --ip=10.0.0.50 --port=4370 --api-url=https://other-api.com/attendance
```

### Dry-run (preview payload, no API call)

```bash
php artisan attendance:fetch --dry-run
```

### Clear device logs after a successful sync

```bash
php artisan attendance:fetch --clear
```

---

## JSON Payload Format

```json
{
  "device_ip": "192.168.1.201",
  "fetched_at": "2025-06-07T10:30:00+06:00",
  "total": 2,
  "records": [
    {
      "uid": 33,
      "user_id": "108",
      "state": 1,
      "timestamp": "2025-06-07 09:00:12",
      "type": 1
    },
    {
      "uid": 34,
      "user_id": "109",
      "state": 2,
      "timestamp": "2025-06-07 17:05:44",
      "type": 15
    }
  ]
}
```

| Field | Description |
|---|---|
| `uid` | Internal device slot index |
| `user_id` | Enrolled user ID (your employee ID) |
| `state` | Punch state: `1` = check-in, `2` = check-out |
| `timestamp` | Date and time of the punch (`Y-m-d H:i:s`) |
| `type` | Verification method: `1` = fingerprint, `15` = face recognition |

The API key is sent as a **Bearer token** in the `Authorization` header.

---

## Scheduling (optional)

To run the sync automatically, add this to `routes/console.php`:

```php
Schedule::command('attendance:fetch --clear')->everyFifteenMinutes();
```

Then run the Laravel scheduler via cron:

```cron
* * * * * cd /path/to/project && php artisan schedule:run >> /dev/null 2>&1
```

---

## Command Options Reference

| Option | Description |
|---|---|
| `--ip` | Device IP (overrides `ZKTECO_IP`) |
| `--port` | Device port (overrides `ZKTECO_PORT`) |
| `--api-url` | API endpoint (overrides `API_URL`) |
| `--date` | Filter logs for a single date (`Y-m-d`) |
| `--from` | Filter logs from this date (`Y-m-d`) |
| `--to` | Filter logs up to this date (`Y-m-d`) |
| `--clear` | Clear device logs after successful sync |
| `--dry-run` | Print payload without calling the API |
