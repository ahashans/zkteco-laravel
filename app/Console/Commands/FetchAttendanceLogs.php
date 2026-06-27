<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Jmrashed\Zkteco\Lib\ZKTeco;

class FetchAttendanceLogs extends Command
{
    protected $signature = 'attendance:fetch
                            {--ip=           : ZKTeco device IP address (overrides ZKTECO_IP)}
                            {--port=         : ZKTeco device port (overrides ZKTECO_PORT)}
                            {--api-url=      : API endpoint URL (overrides API_URL)}
                            {--date=         : Only include logs for this date (Y-m-d)}
                            {--from=         : Only include logs from this date onwards (Y-m-d)}
                            {--to=           : Only include logs up to and including this date (Y-m-d)}
                            {--clear         : Clear attendance logs from device after a successful sync}
                            {--dry-run       : Fetch and display logs without sending to the API}';

    protected $description = 'Fetch attendance logs from a ZKTeco MB20VL device and POST them to an API endpoint';

    public function handle(): int
    {
        $ip       = $this->option('ip')      ?: config('zkteco.device.ip');
        $port     = (int) ($this->option('port') ?: config('zkteco.device.port', 4370));
        $apiUrl   = $this->option('api-url') ?: config('zkteco.api.url');
        $apiKey   = config('zkteco.api.key');
        $apiTimeout = (int) config('zkteco.api.timeout', 30);
        $isDryRun = (bool) $this->option('dry-run');

        if (empty($ip)) {
            $this->error('Device IP address is required. Set ZKTECO_IP in .env or use --ip.');
            return self::FAILURE;
        }

        if (! $isDryRun && empty($apiUrl)) {
            $this->error('API URL is required. Set API_URL in .env or use --api-url.');
            return self::FAILURE;
        }

        $this->info("Connecting to ZKTeco device at {$ip}:{$port} ...");

        $zk = new ZKTeco($ip, $port);
        $connected = false;

        try {
            if (! $zk->connect()) {
                $this->error('Failed to connect to the ZKTeco device. Check IP/port and network connectivity.');
                return self::FAILURE;
            }
            $connected = true;

            $this->info('Connected successfully.');

            // Disable device input while reading to ensure data consistency
            $zk->disableDevice();

            $this->line('Fetching attendance logs...');
            $logs = $zk->getAttendance();

            // Re-enable device so employees can continue checking in/out
            $zk->enableDevice();

            if (empty($logs)) {
                $this->warn('No attendance logs found on the device.');
                return self::SUCCESS;
            }

            $this->info(sprintf('Retrieved %d log(s) from device.', count($logs)));

            $filtered = $this->applyDateFilters($logs);

            if (empty($filtered)) {
                $this->warn('No logs match the specified date filters.');
                return self::SUCCESS;
            }

            if (count($filtered) !== count($logs)) {
                $this->line(sprintf('%d log(s) remaining after date filtering.', count($filtered)));
            }

            $payload = $this->buildPayload($ip, $filtered);

            if ($isDryRun) {
                $this->info('Dry-run mode — payload that would be sent:');
                $this->line(json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                return self::SUCCESS;
            }

            $this->info("Sending payload to: {$apiUrl}");

            $response = $this->postToApi($apiUrl, $apiKey, $apiTimeout, $payload);

            if ($response->successful()) {
                $this->info(sprintf(
                    'Sync successful. HTTP %d — %d record(s) sent.',
                    $response->status(),
                    count($filtered)
                ));

                Log::info('ZKTeco attendance sync successful', [
                    'device_ip' => $ip,
                    'records'   => count($filtered),
                    'status'    => $response->status(),
                ]);

                if ($this->option('clear')) {
                    $this->line('Clearing attendance logs from device...');
                    $zk->clearAttendance();
                    $this->info('Device attendance log cleared.');
                }
            } else {
                $this->error(sprintf('API request failed. HTTP %d', $response->status()));
                $this->error('Response body: ' . $response->body());

                Log::error('ZKTeco attendance sync failed', [
                    'device_ip' => $ip,
                    'status'    => $response->status(),
                    'body'      => $response->body(),
                ]);

                return self::FAILURE;
            }
        } catch (\Exception $e) {
            $this->error('Unexpected error: ' . $e->getMessage());

            Log::error('ZKTeco attendance command exception', [
                'message' => $e->getMessage(),
                'trace'   => $e->getTraceAsString(),
            ]);

            return self::FAILURE;
        } finally {
            if ($connected) {
                $zk->disconnect();
            }
        }

        $this->info('Done.');
        return self::SUCCESS;
    }

    /**
     * Apply optional date range / exact-date filters to the log array.
     *
     * @param  array<int, array<string, mixed>>  $logs
     * @return array<int, array<string, mixed>>
     */
    private function applyDateFilters(array $logs): array
    {
        $date = $this->option('date');
        $from = $this->option('from');
        $to   = $this->option('to');

        if (! $date && ! $from && ! $to) {
            return $logs;
        }

        return array_values(array_filter($logs, function (array $record) use ($date, $from, $to): bool {
            // Timestamps come as "Y-m-d H:i:s" — extract the date part
            $recordDate = substr((string) $record['timestamp'], 0, 10);

            if ($date && $recordDate !== $date) {
                return false;
            }

            if ($from && $recordDate < $from) {
                return false;
            }

            if ($to && $recordDate > $to) {
                return false;
            }

            return true;
        }));
    }

    /**
     * Build the JSON payload to send to the API.
     *
     * Each attendance record from the device contains:
     *   uid       – internal device slot number
     *   id        – the enrolled user ID (mapped to your employee/user ID)
     *   state     – punch state (1 = check-in, 2 = check-out, etc.)
     *   timestamp – date and time of the punch ("Y-m-d H:i:s")
     *   type      – verification type (1 = fingerprint, 15 = face, etc.)
     *
     * @param  array<int, array<string, mixed>>  $logs
     * @return array<string, mixed>
     */
    private function buildPayload(string $deviceIp, array $logs): array
    {
        return [
            'device_ip'  => $deviceIp,
            'fetched_at' => now()->toIso8601String(),
            'total'      => count($logs),
            'records'    => array_map(fn (array $record): array => [
                'uid'       => (int) $record['uid'],
                'user_id'   => (string) $record['id'],
                'state'     => (int) $record['state'],
                'timestamp' => (string) $record['timestamp'],
                'type'      => (int) $record['type'],
            ], $logs),
        ];
    }

    /**
     * POST the payload to the configured API endpoint.
     */
    private function postToApi(string $url, ?string $apiKey, int $timeout, array $payload): Response
    {
        $request = Http::timeout($timeout)
            ->acceptJson()
            ->asJson();

        if (! empty($apiKey)) {
            $request = $request->withToken($apiKey);
        }

        return $request->post($url, $payload);
    }
}
