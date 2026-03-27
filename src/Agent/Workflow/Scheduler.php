<?php

/**
 * Workflow Scheduler — cron for A2E workflows.
 *
 * Two modes:
 * 1. Pseudo-cron: checked on every HTTP request (shared hosting compatible)
 * 2. Real cron: called via CLI (php cli/cron.php) from system crontab
 *
 * Schedules stored as JSON. Each schedule defines:
 * - workflow steps or a saved workflow reference
 * - interval (every_minutes, hourly, daily, weekly, cron expression)
 * - last_run / next_run timestamps
 * - enabled flag
 */

declare(strict_types=1);

namespace ChimeraNoWP\Agent\Workflow;

class Scheduler
{
    private string $storagePath;
    private array $schedules = [];
    private WorkflowEngine $engine;

    public function __construct(WorkflowEngine $engine, string $storagePath)
    {
        $this->engine      = $engine;
        $this->storagePath = rtrim($storagePath, '/');

        if (!is_dir($this->storagePath)) {
            mkdir($this->storagePath, 0755, true);
        }

        $this->load();
    }

    /**
     * Register a scheduled workflow.
     */
    public function schedule(array $definition): array
    {
        $id       = $definition['id'] ?? 'sched_' . uniqid();
        $interval = $definition['interval'] ?? 'hourly';

        $schedule = [
            'id'          => $id,
            'name'        => $definition['name'] ?? $id,
            'description' => $definition['description'] ?? '',
            'steps'       => $definition['steps'] ?? [],
            'input'       => $definition['input'] ?? null,
            'interval'    => $interval,
            'interval_seconds' => self::intervalToSeconds($interval),
            'enabled'     => (bool) ($definition['enabled'] ?? true),
            'last_run'    => null,
            'next_run'    => time(),
            'last_result' => null,
            'run_count'   => 0,
            'error_count' => 0,
            'created_at'  => date('c'),
        ];

        $this->schedules[$id] = $schedule;
        $this->persist();

        return [
            'id'       => $id,
            'name'     => $schedule['name'],
            'interval' => $interval,
            'next_run' => date('c', $schedule['next_run']),
            'status'   => 'scheduled',
        ];
    }

    /**
     * Remove a schedule.
     */
    public function unschedule(string $id): array
    {
        if (!isset($this->schedules[$id])) {
            return ['error' => "Schedule '{$id}' not found."];
        }

        unset($this->schedules[$id]);
        $this->persist();

        return ['id' => $id, 'status' => 'unscheduled'];
    }

    /**
     * Enable/disable a schedule.
     */
    public function setEnabled(string $id, bool $enabled): array
    {
        if (!isset($this->schedules[$id])) {
            return ['error' => "Schedule '{$id}' not found."];
        }

        $this->schedules[$id]['enabled'] = $enabled;
        $this->persist();

        return ['id' => $id, 'enabled' => $enabled];
    }

    /**
     * Tick — check and execute due schedules.
     * Call this on every request (pseudo-cron) or from CLI cron.
     *
     * @return array Results of executed schedules.
     */
    public function tick(): array
    {
        $now     = time();
        $results = [];

        foreach ($this->schedules as $id => &$schedule) {
            if (!$schedule['enabled']) continue;
            if ($schedule['next_run'] > $now) continue;

            // Execute
            $result = $this->engine->run(
                $schedule['steps'],
                $schedule['input']
            );

            // Update schedule
            $schedule['last_run']    = $now;
            $schedule['next_run']    = $now + $schedule['interval_seconds'];
            $schedule['run_count']++;
            $schedule['last_result'] = $result['success'] ? 'success' : 'failed';

            if (!$result['success']) {
                $schedule['error_count']++;
            }

            $results[] = [
                'id'          => $id,
                'name'        => $schedule['name'],
                'success'     => $result['success'],
                'steps_run'   => $result['steps_run'],
                'duration_ms' => $result['duration_ms'],
                'next_run'    => date('c', $schedule['next_run']),
            ];

            // Log execution
            $this->logExecution($id, $result);
        }

        if (!empty($results)) {
            $this->persist();
        }

        return $results;
    }

    /**
     * List all schedules.
     */
    public function list(): array
    {
        return array_map(function ($s) {
            return [
                'id'          => $s['id'],
                'name'        => $s['name'],
                'description' => $s['description'],
                'interval'    => $s['interval'],
                'enabled'     => $s['enabled'],
                'last_run'    => $s['last_run'] ? date('c', $s['last_run']) : null,
                'next_run'    => date('c', $s['next_run']),
                'last_result' => $s['last_result'],
                'run_count'   => $s['run_count'],
                'error_count' => $s['error_count'],
            ];
        }, array_values($this->schedules));
    }

    /**
     * Get execution history for a schedule.
     */
    public function history(string $id, int $limit = 20): array
    {
        $file = $this->storagePath . "/log_{$id}.jsonl";
        if (!file_exists($file)) return [];

        $lines = array_filter(explode("\n", file_get_contents($file)));
        $lines = array_slice(array_reverse($lines), 0, $limit);

        return array_map(fn($l) => json_decode($l, true), $lines);
    }

    // ── Private ────────────────────────────────────────────────────

    private static function intervalToSeconds(string $interval): int
    {
        // Named intervals
        $named = [
            'every_minute'  => 60,
            'every_5min'    => 300,
            'every_15min'   => 900,
            'every_30min'   => 1800,
            'hourly'        => 3600,
            'every_2hours'  => 7200,
            'every_6hours'  => 21600,
            'every_12hours' => 43200,
            'daily'         => 86400,
            'weekly'        => 604800,
        ];

        if (isset($named[$interval])) return $named[$interval];

        // "Nmin" or "Nh" or "Nd" format
        if (preg_match('/^(\d+)(m|min|h|d)$/', $interval, $m)) {
            $n = (int) $m[1];
            return match ($m[2]) {
                'm', 'min' => $n * 60,
                'h'        => $n * 3600,
                'd'        => $n * 86400,
                default    => $n * 60,
            };
        }

        // Raw seconds
        if (is_numeric($interval)) return max(60, (int) $interval);

        return 3600; // default: hourly
    }

    private function logExecution(string $id, array $result): void
    {
        $entry = json_encode([
            'timestamp'   => date('c'),
            'success'     => $result['success'],
            'steps_run'   => $result['steps_run'],
            'duration_ms' => $result['duration_ms'],
            'errors'      => $result['errors'] ?? [],
        ]);

        file_put_contents(
            $this->storagePath . "/log_{$id}.jsonl",
            $entry . "\n",
            FILE_APPEND
        );
    }

    private function persist(): void
    {
        file_put_contents(
            $this->storagePath . '/schedules.json',
            json_encode($this->schedules, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES)
        );
    }

    private function load(): void
    {
        $file = $this->storagePath . '/schedules.json';
        if (!file_exists($file)) return;

        $data = json_decode(file_get_contents($file), true);
        if (is_array($data)) {
            $this->schedules = $data;
        }
    }
}
