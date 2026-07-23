<?php

namespace Ernestdefoe\Connect\Rules;

/**
 * Evaluates a rule's conditions against a fired event's payload. Conditions are
 * [{field, op, value}]; `match` is "all" (AND) or "any" (OR). Field is a key in
 * the flat event payload (e.g. "title", "author", "content").
 */
class Conditions
{
    public const OPERATORS = [
        'equals', 'not_equals', 'contains', 'not_contains',
        'starts_with', 'greater_than', 'less_than', 'is_empty', 'is_not_empty', 'matches',
    ];

    public static function pass(array $conditions, string $match, array $payload): bool
    {
        if (empty($conditions)) {
            return true; // no filters → always run
        }

        $results = array_map(fn ($c) => self::one((array) $c, $payload), $conditions);

        return $match === 'any' ? in_array(true, $results, true) : ! in_array(false, $results, true);
    }

    private static function one(array $c, array $payload): bool
    {
        $actual   = $payload[$c['field'] ?? ''] ?? null;
        $expected = $c['value'] ?? '';
        $a        = is_string($actual) ? mb_strtolower($actual) : $actual;
        $e        = is_string($expected) ? mb_strtolower((string) $expected) : $expected;

        return match ($c['op'] ?? 'equals') {
            'equals'       => (string) $a === (string) $e,
            'not_equals'   => (string) $a !== (string) $e,
            'contains'     => is_string($a) && $e !== '' && str_contains($a, (string) $e),
            'not_contains' => ! (is_string($a) && $e !== '' && str_contains($a, (string) $e)),
            'starts_with'  => is_string($a) && str_starts_with($a, (string) $e),
            'greater_than' => is_numeric($actual) && is_numeric($expected) && $actual > $expected,
            'less_than'    => is_numeric($actual) && is_numeric($expected) && $actual < $expected,
            'is_empty'     => $actual === null || $actual === '' || $actual === [],
            'is_not_empty' => ! ($actual === null || $actual === '' || $actual === []),
            'matches'      => is_string($actual) && @preg_match('/' . str_replace('/', '\/', (string) $expected) . '/i', $actual) === 1,
            default        => false,
        };
    }
}
