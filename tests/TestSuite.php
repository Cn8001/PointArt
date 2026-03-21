<?php

$totalPassed = 0;
$totalFailed = 0;

foreach (scandir(__DIR__) as $file) {
    if (!str_ends_with($file, '.phpt')) continue;

    $content = file_get_contents(__DIR__ . '/' . $file);

    // Extract --FILE-- section
    preg_match('/--FILE--\r?\n(.*?)(?=\r?\n--[A-Z]+--)/s', $content, $fileMatch);
    // Extract --EXPECT-- section
    preg_match('/--EXPECT--\r?\n?(.*?)$/s', $content, $expectMatch);

    $code     = $fileMatch[1]  ?? '';
    $expected = rtrim($expectMatch[1] ?? '');

    // Write to a temp file in the tests directory so __DIR__ resolves correctly
    $tmpFile = __DIR__ . '/_run_' . basename($file, '.phpt') . '.php';
    file_put_contents($tmpFile, $code);
    $actual = rtrim(shell_exec('php ' . escapeshellarg($tmpFile)));
    unlink($tmpFile);

    if ($actual !== '') echo $actual . "\n";

    foreach (explode("\n", $actual) as $line) {
        if (str_starts_with($line, '[PASS]')) $totalPassed++;
        elseif (str_starts_with($line, '[FAIL]')) $totalFailed++;
    }

    if ($actual !== $expected) {
        echo "[MISMATCH] $file\n";
        $totalFailed++;
    }
}

echo "\n" . ($totalFailed === 0 ? "All tests passed." : "$totalFailed test(s) failed.")
   . " ($totalPassed passed, $totalFailed failed)\n";
