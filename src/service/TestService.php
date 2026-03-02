<?php
/**
 * Service for the test route: contains business rules
 * for what message should be displayed.
 */

class TestService
{
    /**
     * Decide which message to display, based on a raw input.
     *
     * Rules (simple but non-trivial):
     * - If input is null or empty after trim: return default "OK".
     * - If input is all lowercase letters (and/or digits/punctuation): return it uppercased.
     * - If input is all uppercase letters (and/or digits/punctuation): return it lowercased.
     * - Otherwise (mixed case, etc.): return input as-is.
     */
    public static function getMessage(?string $raw): string
    {
        if ($raw === null) {
            return 'OK';
        }

        $trimmed = trim($raw);
        if ($trimmed === '') {
            return 'OK';
        }

        $lower = strtolower($trimmed);
        $upper = strtoupper($trimmed);

        // All letters in lowercase (and/or non-letters): flip to uppercase
        if ($trimmed === $lower && $trimmed !== $upper) {
            return $upper;
        }

        // All letters in uppercase (and/or non-letters): flip to lowercase
        if ($trimmed === $upper && $trimmed !== $lower) {
            return $lower;
        }

        // Mixed case or other cases: keep as-is
        return $trimmed;
    }
}
