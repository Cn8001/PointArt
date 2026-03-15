<?php
/**
 * Renderer Tests — view rendering, data extraction, error handling
 * Run: php tests/renderer_test.php
 */

require_once __DIR__ . '/test_helpers.php';
require_once __DIR__ . '/../config.php';
require_once __DIR__ . '/../framework/core/Renderer.php';

use PointStart\Core\Renderer;

// ─── 1. Render a simple view ─────────────────────────────────────────────────

echo "── render simple view ──\n";

$html = Renderer::render('test');
assert_true('Renders test.php without error', is_string($html));
assert_true('Contains echo output', str_contains($html, 'This is a test'));
assert_true('Contains raw HTML', str_contains($html, '<h1> H1 </h1>'));

// ─── 2. Render with data extraction ─────────────────────────────────────────

echo "\n── render with data ──\n";

$html = Renderer::render('renderer_test_view', [
    'title' => 'Hello World',
    'items' => ['Apple', 'Banana', 'Cherry'],
]);

assert_true('Title is rendered', str_contains($html, '<h1>Hello World</h1>'));
assert_true('First item rendered', str_contains($html, '<li>Apple</li>'));
assert_true('Second item rendered', str_contains($html, '<li>Banana</li>'));
assert_true('Third item rendered', str_contains($html, '<li>Cherry</li>'));

// ─── 3. Render same view twice (require vs include_once) ────────────────────

echo "\n── render same view twice ──\n";

$first  = Renderer::render('test');
$second = Renderer::render('test');
assert_equals('Second render produces same output', $first, $second);

// ─── 4. View not found throws exception ─────────────────────────────────────

echo "\n── view not found ──\n";

$threw = false;
try {
    Renderer::render('nonexistent_view_xyz');
} catch (\Exception $e) {
    $threw = true;
    assert_true('Exception message mentions view name', str_contains($e->getMessage(), 'nonexistent_view_xyz'));
}
assert_true('Throws exception for missing view', $threw);

// ─── 5. Empty data array works ──────────────────────────────────────────────

echo "\n── empty data array ──\n";

$html = Renderer::render('test', []);
assert_true('Renders with empty data array', str_contains($html, 'This is a test'));

// ─── 6. Data does not leak between renders ──────────────────────────────────

echo "\n── data isolation ──\n";

$html1 = Renderer::render('renderer_test_view', [
    'title' => 'First',
    'items' => ['A'],
]);
$html2 = Renderer::render('renderer_test_view', [
    'title' => 'Second',
    'items' => ['B'],
]);

assert_true('First render has correct title', str_contains($html1, 'First'));
assert_true('Second render has correct title', str_contains($html2, 'Second'));
assert_true('First render does not contain second data', !str_contains($html1, 'Second'));
assert_true('Second render does not contain first data', !str_contains($html2, 'First'));
