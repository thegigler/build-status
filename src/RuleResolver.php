<?php
declare(strict_types=1);

namespace BuildStatus;

final class RuleResolver
{
    public static function forFile(array $group, string $path): array
    {
        $rule = $group['defaultRule'] ?? [];

        foreach (($group['overrides'] ?? []) as $override) {
            $pattern = $override['match'] ?? null;
            if (!is_string($pattern) || $pattern === '') {
                continue;
            }

            if (preg_match('~' . $pattern . '~i', $path) === 1) {
                $rule = self::merge($rule, $override);
            }
        }

        unset($rule['match']);
        return $rule;
    }

    private static function merge(array $base, array $override): array
    {
        foreach ($override as $key => $value) {
            if (isset($base[$key]) && is_array($base[$key]) && is_array($value)) {
                $base[$key] = self::merge($base[$key], $value);
            } else {
                $base[$key] = $value;
            }
        }
        return $base;
    }
}
