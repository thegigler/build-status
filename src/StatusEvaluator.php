<?php
declare(strict_types=1);

namespace BuildStatus;

use DateTimeImmutable;
use DateTimeZone;

final class StatusEvaluator
{
    public function __construct(
        private DateTimeZone $timezone,
        private int $defaultGraceMinutes = 60
    ) {}

    public function evaluate(string $path, array $rule, DateTimeImmutable $now): array
    {
        if (!file_exists($path)) {
            return $this->result($path, 'missing', 'Missing');
        }

        $mtime = filemtime($path);
        if ($mtime === false) {
            return $this->result($path, 'unknown', 'Unknown');
        }

        $modifiedAt = (new DateTimeImmutable('@' . $mtime))->setTimezone($this->timezone);

        [$scheduledStart, $previousScheduledStart] = Schedule::occurrences(
            $rule['schedule'],
            $now,
            $this->timezone,
            (int)($rule['scheduleOffsetMinutes'] ?? 0)
        );

        $retentionSeconds = (int)($rule['retentionDays'] ?? 3) * 86400;
        $graceSeconds = (int)($rule['graceMinutes'] ?? $this->defaultGraceMinutes) * 60;

        if ($modifiedAt > $scheduledStart) {
            return $this->result(
                $path, 'healthy', 'Healthy',
                $modifiedAt, $scheduledStart, $previousScheduledStart
            );
        }

        if (($now->getTimestamp() - $modifiedAt->getTimestamp()) >= $retentionSeconds) {
            return $this->result(
                $path, 'stale', 'Stale',
                $modifiedAt, $scheduledStart, $previousScheduledStart
            );
        }

        $previousDuration = max(
            0,
            $modifiedAt->getTimestamp() - $previousScheduledStart->getTimestamp()
        );

        $elapsed = max(
            0,
            $now->getTimestamp() - $scheduledStart->getTimestamp()
        );

        if ($elapsed > $previousDuration + $graceSeconds) {
            [$status, $label] = ['very-late', 'Very late'];
        } elseif ($elapsed > $previousDuration) {
            [$status, $label] = ['late', 'Late'];
        } else {
            [$status, $label] = ['running', 'Likely running'];
        }

        return $this->result(
            $path, $status, $label,
            $modifiedAt, $scheduledStart, $previousScheduledStart, $previousDuration
        );
    }

    private function result(
        string $path,
        string $status,
        string $label,
        ?DateTimeImmutable $modifiedAt = null,
        ?DateTimeImmutable $scheduledStart = null,
        ?DateTimeImmutable $previousScheduledStart = null,
        ?int $previousDurationSeconds = null
    ): array {
        return [
            'path' => $path,
            'status' => $status,
            'label' => $label,
            'modifiedAt' => $modifiedAt?->format(DATE_ATOM),
            'scheduledStart' => $scheduledStart?->format(DATE_ATOM),
            'previousScheduledStart' => $previousScheduledStart?->format(DATE_ATOM),
            'previousDurationSeconds' => $previousDurationSeconds
        ];
    }
}
