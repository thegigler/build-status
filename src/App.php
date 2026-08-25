<?php
declare(strict_types=1);

namespace BuildStatus;

use DateTimeImmutable;
use DateTimeZone;

final class App
{
    public function __construct(private readonly array $config) {}

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
                'items' => $items
            ];
        }

        return [
            'generatedAt' => $now->format(DATE_ATOM),
            'timezone' => $timezone->getName(),
            'groups' => $groups
        ];
    }
}
