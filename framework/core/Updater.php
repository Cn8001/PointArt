<?php
/*
 * This Source Code Form is subject to the terms of the Mozilla Public
 * License, v. 2.0. If a copy of the MPL was not distributed with this
 * file, You can obtain one at https://mozilla.org/MPL/2.0/.
 */
namespace PointStart\Core;

class Updater {
    private const GITHUB_API = 'https://api.github.com/repos/Cn8001/PointArt/releases/latest';
    private const VERSION_FILE = __DIR__ . '/../VERSION';

    private string $secret;
    private string $rootDir;

    /** Files/dirs that must never be overwritten by the updater */
    private const PROTECTED = [
        'app',
        '.env',
        '.env.example',
        'cache',
    ];

    public function __construct(string $secret) {
        $this->secret = $secret;
        $this->rootDir = realpath(__DIR__ . '/../../');
    }

    // ── Public entry points called from App ──

    public function handleLoginForm(): void {
        if ($this->isAuthenticated()) {
            $this->showCheckPage();
            return;
        }
        echo $this->renderLoginPage();
    }

    public function handleLogin(): void {
        if (!$this->validateSecret($_POST['secret'] ?? '')) {
            http_response_code(403);
            echo $this->renderErrorPage('Invalid secret.');
            return;
        }

        $this->authenticate();
        header('Location: /pointart/update');
        exit;
    }

    public function handleRunUpdate(): void {
        if (!$this->isAuthenticated()) {
            header('Location: /pointart/update');
            exit;
        }

        $current = $this->getCurrentVersion();
        $latest  = $this->fetchLatestRelease();

        if (isset($latest['error'])) {
            echo $this->renderErrorPage($latest['error']);
            return;
        }

        if (version_compare($current, $latest['version'], '>=')) {
            echo $this->renderResultPage(true, 'Already up to date (v' . $current . ').');
            return;
        }

        $result = $this->executeUpdate($latest);
        echo $this->renderResultPage($result['success'], $result['message']);
    }

    private function showCheckPage(): void {
        $current = $this->getCurrentVersion();
        $latest  = $this->fetchLatestRelease();

        if (isset($latest['error'])) {
            echo $this->renderErrorPage($latest['error']);
            return;
        }

        echo $this->renderCheckPage($current, $latest);
    }

    // ── Version ──

    public function getCurrentVersion(): string {
        if (!is_file(self::VERSION_FILE)) return '0.0.0';

        $raw = trim((string) file_get_contents(self::VERSION_FILE));
        if ($raw === '') return '0.0.0';

        if (preg_match('/\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?/', $raw, $matches) === 1) {
            return $matches[0];
        }

        return $raw;
    }

    // ── GitHub API ──

    private function fetchLatestRelease(): ?array {
        $context = stream_context_create([
            'http' => [
                'header' => "User-Agent: PointArt-Updater\r\n",
                'timeout' => 15,
            ],
        ]);

        $json = @file_get_contents(self::GITHUB_API, false, $context);
        if ($json === false) return ['error' => 'Could not reach GitHub. Check that your server allows outbound HTTPS requests.'];

        $data = json_decode($json, true);
        if (!is_array($data) || empty($data['tag_name'])) return ['error' => 'Unexpected response from GitHub API.'];

        $tag = $data['tag_name'];
        if (preg_match('/\d+\.\d+\.\d+(?:-[0-9A-Za-z.-]+)?(?:\+[0-9A-Za-z.-]+)?/', $tag, $m) !== 1) {
            return ['error' => 'Latest release tag "' . htmlspecialchars($tag) . '" is not a valid version. Releases must use semver tags (e.g. v1.2.0).'];
        }
        $version = $m[0];

        return [
            'version'  => $version,
            'tag'      => $data['tag_name'],
            'notes'    => $data['body'] ?? '',
            'zip_url'  => $data['zipball_url'] ?? '',
            'date'     => $data['published_at'] ?? '',
        ];
    }

    // ── Update execution ──

    private function executeUpdate(array $release): array {
        $zipUrl = $release['zip_url'];
        if (empty($zipUrl)) {
            return ['success' => false, 'message' => 'No download URL found in release.'];
        }

        // Download zip
        $context = stream_context_create([
            'http' => [
                'header'  => "User-Agent: PointArt-Updater\r\n",
                'timeout' => 60,
            ],
        ]);

        $zipData = @file_get_contents($zipUrl, false, $context);
        if ($zipData === false) {
            return ['success' => false, 'message' => 'Failed to download release archive.'];
        }

        $tmpZip = $this->rootDir . '/cache/pointart-update.zip';
        $tmpDir = $this->rootDir . '/cache/pointart-update-tmp';

        if (!is_dir(dirname($tmpZip))) {
            mkdir(dirname($tmpZip), 0755, true);
        }
        file_put_contents($tmpZip, $zipData);

        // Extract
        $zip = new \ZipArchive();
        if ($zip->open($tmpZip) !== true) {
            @unlink($tmpZip);
            return ['success' => false, 'message' => 'Failed to open downloaded archive.'];
        }

        if (is_dir($tmpDir)) $this->removeDirectory($tmpDir);
        mkdir($tmpDir, 0755, true);
        $zip->extractTo($tmpDir);
        $zip->close();
        @unlink($tmpZip);

        // GitHub zips have a top-level directory (e.g. Cn8001-PointArt-abc1234/)
        $extracted = glob($tmpDir . '/*', GLOB_ONLYDIR);
        if (empty($extracted)) {
            $this->removeDirectory($tmpDir);
            return ['success' => false, 'message' => 'Unexpected archive structure.'];
        }
        $sourceDir = $extracted[0];

        // Create backup
        $backupDir = $this->rootDir . '/cache/update-backup-' . $this->getCurrentVersion();
        if (is_dir($backupDir)) $this->removeDirectory($backupDir);
        mkdir($backupDir, 0755, true);

        // Copy files: backup existing, then overwrite
        $errors = [];
        $this->syncDirectory($sourceDir, $this->rootDir, $backupDir, $errors);

        // Clean up temp
        $this->removeDirectory($tmpDir);

        // Clear route cache
        ClassLoader::clearCache();

        if (!empty($errors)) {
            return [
                'success' => false,
                'message' => 'Update partially applied. Errors: ' . implode('; ', $errors)
                           . ' A backup of original files is at cache/update-backup-' . $this->getCurrentVersion() . '/',
            ];
        }

        return [
            'success' => true,
            'message' => 'Updated to v' . $release['version'] . '. Backup saved to cache/update-backup-' . $release['version'] . '/',
        ];
    }

    /**
     * Recursively sync $source into $destination, backing up overwritten files.
     */
    private function syncDirectory(string $source, string $destination, string $backupDir, array &$errors, string $relativePath = ''): void {
        $items = scandir($source);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;

            $rel  = $relativePath === '' ? $item : $relativePath . '/' . $item;
            $src  = $source . '/' . $item;
            $dest = $destination . '/' . $item;
            $bak  = $backupDir . '/' . $item;

            // Skip protected files/dirs
            if ($this->isProtected($rel)) continue;

            // Skip sqlite files anywhere
            if (pathinfo($item, PATHINFO_EXTENSION) === 'sqlite') continue;

            if (is_dir($src)) {
                if (!is_dir($dest)) {
                    mkdir($dest, 0755, true);
                }
                if (!is_dir($bak)) {
                    mkdir($bak, 0755, true);
                }
                $this->syncDirectory($src, $dest, $bak, $errors, $rel);
            } else {
                // Backup existing file
                if (is_file($dest)) {
                    $bakParent = dirname($bak);
                    if (!is_dir($bakParent)) mkdir($bakParent, 0755, true);
                    if (!@copy($dest, $bak)) {
                        $errors[] = "Failed to backup $rel";
                        continue;
                    }
                }
                // Copy new file
                $destParent = dirname($dest);
                if (!is_dir($destParent)) mkdir($destParent, 0755, true);
                if (!@copy($src, $dest)) {
                    $errors[] = "Failed to copy $rel";
                }
            }
        }
    }

    private function isProtected(string $relativePath): bool {
        $first = explode('/', $relativePath)[0];
        return in_array($first, self::PROTECTED, true);
    }

    private function removeDirectory(string $dir): void {
        if (!is_dir($dir)) return;
        $items = scandir($dir);
        foreach ($items as $item) {
            if ($item === '.' || $item === '..') continue;
            $path = $dir . '/' . $item;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }

    // ── Auth ──

    private function ensureSession(): void {
        if (session_status() === PHP_SESSION_NONE) {
            session_start();
        }
    }

    private function validateSecret(string $input): bool {
        if (empty($this->secret)) return false;
        return hash_equals($this->secret, $input);
    }

    private function authenticate(): void {
        $this->ensureSession();
        $_SESSION['_pointart_updater_auth'] = true;
    }

    private function isAuthenticated(): bool {
        $this->ensureSession();
        return !empty($_SESSION['_pointart_updater_auth']);
    }

    // ── HTML Rendering ──

    private function renderLoginPage(): string {
        return $this->layout('PointArt Updater', '
            <h1>PointArt Updater</h1>
            <p>Enter your updater secret to continue.</p>
            <form method="POST" action="/pointart/update">
                <label for="secret">Secret</label>
                <input type="password" id="secret" name="secret" required autofocus>
                <button type="submit">Continue</button>
            </form>
        ');
    }

    private function renderCheckPage(string $current, array $latest): string {
        $isUpToDate = version_compare($current, $latest['version'], '>=');
        $notes = htmlspecialchars($latest['notes']);
        $date  = $latest['date'] ? date('F j, Y', strtotime($latest['date'])) : '';

        $updateButton = '';
        if (!$isUpToDate) {
            $updateButton = '
                <form method="POST" action="/pointart/update/run">
                    <button type="submit" class="btn-update" onclick="this.disabled=true;this.textContent=\'Updating…\';this.form.submit();">Update to v' . htmlspecialchars($latest['version']) . '</button>
                </form>';
        }

        $statusClass = $isUpToDate ? 'up-to-date' : 'update-available';
        $statusText  = $isUpToDate ? 'Up to date' : 'Update available';

        return $this->layout('PointArt Updater', '
            <h1>PointArt Updater</h1>
            <div class="version-info">
                <div class="version-row">
                    <span class="label">Installed version</span>
                    <span class="value">v' . htmlspecialchars($current) . '</span>
                </div>
                <div class="version-row">
                    <span class="label">Latest version</span>
                    <span class="value">v' . htmlspecialchars($latest['version']) . '</span>
                </div>
                <div class="version-row">
                    <span class="label">Status</span>
                    <span class="value ' . $statusClass . '">' . $statusText . '</span>
                </div>
                ' . ($date ? '<div class="version-row"><span class="label">Released</span><span class="value">' . $date . '</span></div>' : '') . '
            </div>
            ' . ($notes ? '<div class="release-notes"><h2>Release Notes</h2><pre>' . $notes . '</pre></div>' : '') . '
            ' . $updateButton . '
            <a href="/pointart/update" class="btn-secondary" style="display:inline-block;margin-top:1rem;text-decoration:none;">Refresh</a>
        ');
    }

    private function renderResultPage(bool $success, string $message): string {
        $icon  = $success ? '&#10003;' : '&#10007;';
        $class = $success ? 'success' : 'error';

        return $this->layout('PointArt Updater', '
            <h1>PointArt Updater</h1>
            <div class="result ' . $class . '">
                <span class="icon">' . $icon . '</span>
                <p>' . htmlspecialchars($message) . '</p>
            </div>
            <a href="/pointart/update" class="btn-secondary" style="display:inline-block;margin-top:1rem;text-decoration:none;">Back to Updater</a>
        ');
    }

    private function renderErrorPage(string $message): string {
        return $this->renderResultPage(false, $message);
    }

    private function layout(string $title, string $body): string {
        return '<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>' . htmlspecialchars($title) . '</title>
    <style>
        * { margin: 0; padding: 0; box-sizing: border-box; }
        body { font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, sans-serif; background: #f5f5f5; color: #333; display: flex; justify-content: center; padding: 3rem 1rem; }
        h1 { margin-bottom: 1.5rem; font-size: 1.5rem; }
        h2 { margin-bottom: 0.75rem; font-size: 1.1rem; }
        .container { background: #fff; border-radius: 8px; box-shadow: 0 2px 8px rgba(0,0,0,0.08); padding: 2rem; max-width: 520px; width: 100%; }
        label { display: block; font-weight: 600; margin-bottom: 0.4rem; font-size: 0.9rem; }
        input[type="password"] { width: 100%; padding: 0.6rem; border: 1px solid #ccc; border-radius: 4px; font-size: 1rem; margin-bottom: 1rem; }
        button { padding: 0.6rem 1.4rem; border: none; border-radius: 4px; font-size: 0.95rem; cursor: pointer; }
        button[type="submit"] { background: #2563eb; color: #fff; }
        button[type="submit"]:hover { background: #1d4ed8; }
        .btn-update { background: #16a34a; color: #fff; font-weight: 600; padding: 0.7rem 1.6rem; }
        .btn-update:hover { background: #15803d; }
        .btn-secondary { background: #e5e7eb; color: #333; }
        .btn-secondary:hover { background: #d1d5db; }
        .version-info { margin-bottom: 1.5rem; }
        .version-row { display: flex; justify-content: space-between; padding: 0.5rem 0; border-bottom: 1px solid #f0f0f0; }
        .version-row .label { color: #666; }
        .version-row .value { font-weight: 600; }
        .up-to-date { color: #16a34a; }
        .update-available { color: #ea580c; }
        .release-notes { background: #f9fafb; border: 1px solid #e5e7eb; border-radius: 4px; padding: 1rem; margin-bottom: 1.5rem; }
        .release-notes pre { white-space: pre-wrap; word-wrap: break-word; font-size: 0.85rem; line-height: 1.5; }
        .result { padding: 1.2rem; border-radius: 6px; display: flex; align-items: flex-start; gap: 0.75rem; }
        .result.success { background: #f0fdf4; border: 1px solid #bbf7d0; }
        .result.error { background: #fef2f2; border: 1px solid #fecaca; }
        .result .icon { font-size: 1.3rem; line-height: 1; }
        .result.success .icon { color: #16a34a; }
        .result.error .icon { color: #dc2626; }
    </style>
</head>
<body>
    <div class="container">' . $body . '</div>
</body>
</html>';
    }
}
?>
