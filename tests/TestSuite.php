<?php

$passed = 0;
$failed = 0;
foreach (scandir(__DIR__) as $file){
    if($file === '.' || $file === '..' || $file === 'TestSuite.php' || !str_ends_with($file,'_test.php')) continue; // Only include test files
    require_once __DIR__ . '/' . $file;
    
}
// ─── Summary ─────────────────────────────────────────────────────────────────

echo "\n" . ($failed === 0 ? "All tests passed." : "$failed test(s) failed.")
   . " ($passed passed, $failed failed)\n";

?>