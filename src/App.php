<?php
declare(strict_types=1);

namespace BuildStatus;

use DateTimeImmutable;
use DateTimeZone;

final class App
{
    public function __construct(private array $config) {}

    public function snapshot(?DateTimeImmutable $now = null): array
    {
        $timezone = new DateTimeZone($this->config['timezone']);
        $now ??= new DateTimeImmutable('now', $timezone);

        $evaluator = new StatusEvaluator(
            $timezone,
            (int)($this->config['defaultGraceMinutes'] ?? 60)
        );

        $groups = [];
        foreach ($this->config['groups'] as $group) {
            $items = [];
            foreach (($group['files'] ?? []) as $path) {
                $items[] = $evaluator->evaluate(
                    $path,
                    RuleResolver::forFile($group, $path),
                    $now
                );
            }

            $groups[] = [
                'id' => $group['id'] ?? null,
                'label' => $group['label'] ?? ($group['id'] ?? 'Builds'),
                'status' => $this->worstStatus($items),
                'items' => $items
            ];
        }

        return [
            'generatedAt' => $now->format(DATE_ATOM),
            'timezone' => $timezone->getName(),
            'groups' => $groups
        ];
    }

    private function worstStatus(array $items): array
    {
        $severity = [
            'missing' => 6,
            'very-late' => 5,
            'late' => 4,
            'stale' => 3,
            'unknown' => 2,
            'running' => 1,
            'healthy' => 0
        ];

        $worst = null;
        foreach ($items as $item) {
            if ($worst === null || ($severity[$item['status']] ?? 0) > ($severity[$worst['status']] ?? 0)) {
                $worst = $item;
            }
        }

        return [
            'status' => $worst['status'] ?? 'unknown',
            'label' => $worst['label'] ?? 'Unknown'
        ];
    }
}
