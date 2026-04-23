<?php
/**
 * Export first N HERO sections to HTML + ZIP bundles.
 *
 * Usage:
 *   php tools/export-sections.php
 *   php tools/export-sections.php "C:\Users\user\Desktop\Projects\FigmaProject" 3
 */

declare(strict_types=1);

$defaultTarget = 'C:\Users\user\Desktop\Projects\FigmaProject';
$targetRoot    = $argv[1] ?? $defaultTarget;
$limit         = isset($argv[2]) ? max(1, (int) $argv[2]) : 3;

$repoRoot    = dirname(__DIR__);
$sectionsDir = $repoRoot . DIRECTORY_SEPARATOR . 'sections';

if (!is_dir($sectionsDir)) {
    fwrite(STDERR, "Sections directory not found: {$sectionsDir}\n");
    exit(1);
}

if (!class_exists('ZipArchive')) {
    fwrite(STDERR, "ZipArchive extension is required.\n");
    exit(1);
}

if (!is_dir($targetRoot) && !mkdir($targetRoot, 0777, true) && !is_dir($targetRoot)) {
    fwrite(STDERR, "Could not create target directory: {$targetRoot}\n");
    exit(1);
}

file_put_contents(
    rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . 'export-debug.log',
    '[' . date('c') . "] exporter-start target={$targetRoot} limit={$limit}\n",
    FILE_APPEND
);

$allSectionDirs = array_filter(glob($sectionsDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [], static function (string $dir): bool {
    return is_file($dir . DIRECTORY_SEPARATOR . 'schema.php');
});

sort($allSectionDirs, SORT_NATURAL | SORT_FLAG_CASE);
$picked = array_slice($allSectionDirs, 0, $limit);

if (count($picked) === 0) {
    fwrite(STDERR, "No sections with schema.php found.\n");
    exit(1);
}

$summary = [
    'generated_at' => date('c'),
    'target_root'  => $targetRoot,
    'picked_count' => count($picked),
    'sections'     => [],
];

foreach ($picked as $sectionDir) {
    $slug = basename($sectionDir);
    $outDir = rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . $slug;
    @mkdir($outDir, 0777, true);

    $copied = [];
    foreach (['schema.php', 'section.php', 'style.css', 'script.js'] as $name) {
        $src = $sectionDir . DIRECTORY_SEPARATOR . $name;
        if (is_file($src)) {
            copy($src, $outDir . DIRECTORY_SEPARATOR . $name);
            $copied[] = $name;
        }
    }

    $mediaFiles = collectMediaFiles($sectionDir);
    foreach ($mediaFiles as $mediaPath) {
        $rel = ltrim(str_replace($sectionDir, '', $mediaPath), "\\/");
        $dst = $outDir . DIRECTORY_SEPARATOR . $rel;
        @mkdir(dirname($dst), 0777, true);
        copy($mediaPath, $dst);
    }

    $schemaPath = $sectionDir . DIRECTORY_SEPARATOR . 'schema.php';
    $schema = includeSchemaArray($schemaPath);
    $defaults = extractDefaults($schema);
    $html = buildPreviewHtml($slug, $schema, $defaults, $sectionDir);
    file_put_contents($outDir . DIRECTORY_SEPARATOR . 'section.html', $html);

    $zipPath = rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . $slug . '.zip';
    createZipFromDirectory($outDir, $zipPath);

    $summary['sections'][] = [
        'slug'         => $slug,
        'source_dir'   => $sectionDir,
        'output_dir'   => $outDir,
        'zip'          => $zipPath,
        'copied_files' => $copied,
        'media_count'  => count($mediaFiles),
    ];

    echo "Exported {$slug} -> {$zipPath}" . PHP_EOL;
}

$summaryPath = rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . 'export-summary.json';
file_put_contents($summaryPath, json_encode($summary, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Summary written: {$summaryPath}" . PHP_EOL;
exit(0);

/**
 * @return array<string,mixed>
 */
function includeSchemaArray(string $schemaPath): array
{
    if (!is_file($schemaPath)) {
        return [];
    }
    $loaded = include $schemaPath;
    return is_array($loaded) ? $loaded : [];
}

/**
 * @param array<string,mixed> $schema
 * @return array<string,mixed>
 */
function extractDefaults(array $schema): array
{
    $out = [];
    $settings = $schema['settings'] ?? [];
    if (!is_array($settings)) {
        return $out;
    }
    foreach ($settings as $field) {
        if (!is_array($field) || empty($field['id'])) {
            continue;
        }
        $id = (string) $field['id'];
        $out[$id] = $field['default'] ?? '';
    }
    return $out;
}

/**
 * @return string[]
 */
function collectMediaFiles(string $sectionDir): array
{
    $mediaExt = ['png', 'jpg', 'jpeg', 'gif', 'webp', 'svg', 'avif', 'mp4', 'webm'];
    $out = [];
    $it = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sectionDir, FilesystemIterator::SKIP_DOTS)
    );
    foreach ($it as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $ext = strtolower($file->getExtension());
        if (in_array($ext, $mediaExt, true)) {
            $out[] = $file->getPathname();
        }
    }
    return $out;
}

/**
 * @param array<string,mixed> $schema
 * @param array<string,mixed> $defaults
 */
function buildPreviewHtml(string $slug, array $schema, array $defaults, string $sectionDir): string
{
    $label = (string)($schema['label'] ?? $slug);
    $rows = '';
    foreach ($defaults as $k => $v) {
        $val = is_scalar($v) ? (string) $v : json_encode($v, JSON_UNESCAPED_UNICODE);
        $rows .= '<li><strong>' . htmlspecialchars((string)$k) . ':</strong> ' . htmlspecialchars((string)$val) . '</li>';
    }
    if ($rows === '') {
        $rows = '<li>No schema defaults found.</li>';
    }

    $rawSection = '';
    $sectionPhp = $sectionDir . DIRECTORY_SEPARATOR . 'section.php';
    if (is_file($sectionPhp)) {
        $rawSection = file_get_contents($sectionPhp) ?: '';
    }
    $htmlOnly = preg_replace('/<\?php[\s\S]*?\?>/m', '', $rawSection ?? '') ?? '';
    $htmlOnly = trim($htmlOnly);
    if ($htmlOnly === '') {
        $htmlOnly = '<p>Template uses PHP rendering logic. See section.php for full output.</p>';
    }

    return '<!doctype html><html><head><meta charset="utf-8"><title>'
        . htmlspecialchars($label)
        . '</title>'
        . '<meta name="viewport" content="width=device-width, initial-scale=1">'
        . '<link rel="stylesheet" href="style.css">'
        . '<style>body{font-family:Arial,sans-serif;padding:24px;max-width:1100px;margin:0 auto}pre{white-space:pre-wrap;background:#f6f7f8;border:1px solid #ddd;padding:12px}</style>'
        . '</head><body>'
        . '<h1>' . htmlspecialchars($label) . ' (' . htmlspecialchars($slug) . ')</h1>'
        . '<h3>Schema defaults</h3><ul>' . $rows . '</ul>'
        . '<h3>Template HTML approximation</h3>'
        . '<div class="hero-export-preview">' . $htmlOnly . '</div>'
        . '<h3>Raw section.php</h3><pre>' . htmlspecialchars($rawSection) . '</pre>'
        . '</body></html>';
}

function createZipFromDirectory(string $sourceDir, string $zipPath): void
{
    $zip = new ZipArchive();
    if (is_file($zipPath)) {
        @unlink($zipPath);
    }
    if ($zip->open($zipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== true) {
        throw new RuntimeException("Cannot create zip: {$zipPath}");
    }
    $files = new RecursiveIteratorIterator(
        new RecursiveDirectoryIterator($sourceDir, FilesystemIterator::SKIP_DOTS),
        RecursiveIteratorIterator::LEAVES_ONLY
    );
    foreach ($files as $file) {
        if (!$file instanceof SplFileInfo || !$file->isFile()) {
            continue;
        }
        $filePath = $file->getPathname();
        $relative = ltrim(str_replace($sourceDir, '', $filePath), "\\/");
        $zip->addFile($filePath, $relative);
    }
    $zip->close();
}
