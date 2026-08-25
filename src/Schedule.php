<?php
declare(strict_types=1);

namespace BuildStatus;

use DateTimeImmutable;
use DateTimeZone;
use InvalidArgumentException;

final class Schedule
{
    public static function occurrences(
        array $schedule,
        DateTimeImmutable $now,
        DateTimeZone $timezone,
        int $offsetMinutes = 0
    ): array {
        return match ($schedule['type'] ?? null) {
            'daily' => self::daily($schedule, $now, $timezone, $offsetMinutes),
            'weekly' => self::weekly($schedule, $now, $timezone, $offsetMinutes),
            default => throw new InvalidArgumentException('Unsupported schedule type')
        };
    }

    private static function daily(
        array $schedule,
        DateTimeImmutable $now,
        DateTimeZone $timezone,
        int $offsetMinutes
    ): array {
        $times = $schedule['times'] ?? [];
        if (!$times) {
            throw new InvalidArgumentException('Daily schedule requires times.');
        }

        $candidates = [];
        $today = $now->setTimezone($timezone)->setTime(0, 0, 0);

        foreach ([-1, 0] as $dayOffset) {
            $date = $today->modify(($dayOffset >= 0 ? '+' : '') . $dayOffset . ' day');
            foreach ($times as $time) {
                if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', (string)$time, $m)) {
                    throw new InvalidArgumentException("Invalid time: {$time}");
                }

                $candidate = $date
                    ->setTime((int)$m[1], (int)$m[2], 0)
                    ->modify(($offsetMinutes >= 0 ? '+' : '') . $offsetMinutes . ' minutes');

                if ($candidate <= $now) {
                    $candidates[] = $candidate;
                }
            }
        }

        usort($candidates, fn(DateTimeImmutable $a, DateTimeImmutable $b) => $a <=> $b);

        if (count($candidates) < 2) {
            throw new InvalidArgumentException('Could not resolve two daily occurrences.');
        }

        $last = array_key_last($candidates);
        return [$candidates[$last], $candidates[$last - 1]];
    }

    private static function weekly(
        array $schedule,
        DateTimeImmutable $now,
        DateTimeZone $timezone,
        int $offsetMinutes
    ): array {
        $weekday = (string)($schedule['weekday'] ?? '');
        $time = (string)($schedule['time'] ?? '');

        if (!preg_match('/^([01]\d|2[0-3]):([0-5]\d)$/', $time, $m)) {
            throw new InvalidArgumentException("Invalid weekly time: {$time}");
        }

        $localNow = $now->setTimezone($timezone);
        $todayCandidate = $localNow->setTime((int)$m[1], (int)$m[2], 0);

        if (strcasecmp($localNow->format('l'), $weekday) === 0 && $todayCandidate <= $localNow) {
            $current = $todayCandidate;
        } else {
            $current = (new DateTimeImmutable("last {$weekday} {$time}", $timezone))
                ->setTime((int)$m[1], (int)$m[2], 0);
        }

        $current = $current->modify(($offsetMinutes >= 0 ? '+' : '') . $offsetMinutes . ' minutes');
        return [$current, $current->modify('-7 days')];
    }
}
