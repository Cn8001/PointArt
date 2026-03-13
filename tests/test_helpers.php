<?php
/**
 * Shared test helpers — included by individual test files and TestSuite.
 */



if (!function_exists('assert_true')) {
    function assert_true(string $label, bool $condition): void {
        global $passed, $failed;
        if ($condition) { echo "[PASS] $label\n"; $passed++; }
        else             { echo "[FAIL] $label\n"; $failed++; }
    }
}

if (!function_exists('assert_equals')) {
    function assert_equals(string $label, mixed $expected, mixed $actual): void {
        global $passed, $failed;
        if ($expected === $actual) {
            echo "[PASS] $label\n";
            $passed++;
        } else {
            echo "[FAIL] $label — expected " . var_export($expected, true)
               . ", got " . var_export($actual, true) . "\n";
            $failed++;
        }
    }
}
