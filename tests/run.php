<?php
declare(strict_types=1);

use BuildStatus\Schedule;
use BuildStatus\StatusEvaluator;

require dirname(__DIR__) . '/src/bootstrap.php';

function assertSameValue(mixed $expected, mixed $actual, string $message): void {
    if ($expected !== $actual) {
        fwrite(STDERR, "FAIL: {$message}\nExpected: " . var_export($expected, true) . "\nActual: " . var_export($actual, true) . "\n");
        exit(1);
    }
    echo "PASS: {$message}\n";
}

$tz = new DateTimeZone('America/Los_Angeles');

[$current, $previous] = Schedule::occurrences(
    ['type' => 'daily', 'times' => ['00:00', '04:00', '12:00', '17:00']],
    new DateTimeImmutable('2026-08-25 15:00:00', $tz),
    $tz
);

assertSameValue('2026-08-25 12:00', $current->format('Y-m-d H:i'), 'daily current occurrence');
assertSameValue('2026-08-25 04:00', $previous->format('Y-m-d H:i'), 'daily previous occurrence');

$tmp = tempnam(sys_get_temp_dir(), 'build-status-');
touch($tmp, (new DateTimeImmutable('2026-08-25 12:30:00', $tz))->getTimestamp());

$result = (new StatusEvaluator($tz))->evaluate(
    $tmp,
    ['schedule' => ['type' => 'daily', 'times' => ['00:00', '04:00', '12:00', '17:00']], 'retentionDays' => 3],
    new DateTimeImmutable('2026-08-25 15:00:00', $tz)
);

assertSameValue('healthy', $result['status'], 'artifact after current schedule is healthy');
unlink($tmp);

echo "All tests passed.\n";
