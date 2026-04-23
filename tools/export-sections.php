<?php
/**
 * Export first N HERO sections to HTML + ZIP bundles.
 *
 * Usage:
 *   php tools/export-sections.php
 *   php tools/export-sections.php "C:\Users\user\Desktop\Projects\FigmaProject" 3
 */

declare(strict_types=1);

// Allow including plugin schema files that guard with `defined( 'ABSPATH' ) || exit;`.
if (!defined('ABSPATH')) {
    define('ABSPATH', dirname(__DIR__) . DIRECTORY_SEPARATOR);
}

$defaultTarget = 'C:\Users\user\Desktop\Projects\FigmaProject\sections-export';
$targetRoot    = $argv[1] ?? $defaultTarget;
$limitArg      = $argv[2] ?? 'all';
$limit         = parseLimit($limitArg);

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

$debugLog = rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . 'export-debug.log';
file_put_contents($debugLog, '[' . date('c') . "] exporter-start target={$targetRoot} limit_arg={$limitArg}\n", FILE_APPEND);

$allSectionDirs = array_filter(glob($sectionsDir . DIRECTORY_SEPARATOR . '*', GLOB_ONLYDIR) ?: [], static function (string $dir): bool {
    return is_file($dir . DIRECTORY_SEPARATOR . 'schema.php');
});

sort($allSectionDirs, SORT_NATURAL | SORT_FLAG_CASE);
$picked = $limit === null ? $allSectionDirs : array_slice($allSectionDirs, 0, $limit);

if (count($picked) === 0) {
    fwrite(STDERR, "No sections with schema.php found.\n");
    exit(1);
}

$report = [
    'generated_at' => date('c'),
    'target_root'  => $targetRoot,
    'limit_arg'    => $limitArg,
    'picked_count' => count($picked),
    'sections'     => [],
];

foreach ($picked as $sectionDir) {
    $slug = basename($sectionDir);
    $outDir = rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . $slug;
    @mkdir($outDir, 0777, true);

    $sectionReport = [
        'slug'            => $slug,
        'source_dir'      => $sectionDir,
        'output_dir'      => $outDir,
        'required_files'  => [],
        'validation'      => [],
        'status'          => 'pending',
        'zip'             => null,
        'media_count'     => 0,
    ];

    $requiredFiles = ['schema.php', 'section.php', 'style.css'];
    foreach ($requiredFiles as $requiredFile) {
        $exists = is_file($sectionDir . DIRECTORY_SEPARATOR . $requiredFile);
        $sectionReport['required_files'][$requiredFile] = $exists;
    }
    $missingRequired = array_keys(array_filter(
        $sectionReport['required_files'],
        static fn (bool $exists): bool => $exists === false
    ));
    if ($missingRequired !== []) {
        $sectionReport['status'] = 'failed_required_files';
        $sectionReport['validation']['missing_required_files'] = $missingRequired;
        $report['sections'][] = $sectionReport;
        file_put_contents($debugLog, '[' . date('c') . "] skip {$slug}: missing required files: " . implode(', ', $missingRequired) . "\n", FILE_APPEND);
        continue;
    }

    $schemaPath = $sectionDir . DIRECTORY_SEPARATOR . 'schema.php';
    $schema = includeSchemaArray($schemaPath);
    if (!is_array($schema) || $schema === []) {
        $sectionReport['status'] = 'failed_schema_parse';
        $sectionReport['validation']['schema_error'] = 'schema.php must return a non-empty array';
        $report['sections'][] = $sectionReport;
        file_put_contents($debugLog, '[' . date('c') . "] skip {$slug}: invalid schema array\n", FILE_APPEND);
        continue;
    }
    $schemaType = (string)($schema['type'] ?? '');
    $sectionReport['validation']['schema_type'] = $schemaType;
    if ($schemaType !== $slug) {
        $sectionReport['status'] = 'failed_schema_type_mismatch';
        $sectionReport['validation']['schema_type_mismatch'] = sprintf('Expected "%s", got "%s"', $slug, $schemaType);
        $report['sections'][] = $sectionReport;
        file_put_contents($debugLog, '[' . date('c') . "] skip {$slug}: schema type mismatch (got {$schemaType})\n", FILE_APPEND);
        continue;
    }

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

    $defaults = extractDefaults($schema);
    $html = buildPreviewHtml($slug, $schema, $defaults, $sectionDir);
    file_put_contents($outDir . DIRECTORY_SEPARATOR . 'section.html', $html);

    $zipPath = rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . $slug . '.zip';
    createZipFromDirectory($outDir, $zipPath);
    $zipValidation = validateZipHasRequiredFiles($zipPath, ['schema.php', 'section.php', 'style.css']);
    if ($zipValidation !== []) {
        $sectionReport['status'] = 'failed_zip_validation';
        $sectionReport['validation']['zip_missing_files'] = $zipValidation;
        $sectionReport['zip'] = $zipPath;
        $report['sections'][] = $sectionReport;
        file_put_contents($debugLog, '[' . date('c') . "] invalid zip {$slug}: missing in zip: " . implode(', ', $zipValidation) . "\n", FILE_APPEND);
        continue;
    }

    $sectionReport['status'] = 'zipped';
    $sectionReport['zip'] = $zipPath;
    $sectionReport['copied_files'] = $copied;
    $sectionReport['media_count'] = count($mediaFiles);
    $report['sections'][] = $sectionReport;

    echo "Exported {$slug} -> {$zipPath}" . PHP_EOL;
}

$summaryPath = rtrim($targetRoot, "\\/") . DIRECTORY_SEPARATOR . 'export-report.json';
file_put_contents($summaryPath, json_encode($report, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
echo "Summary written: {$summaryPath}" . PHP_EOL;
exit(0);

/**
 * @return int|null
 */
function parseLimit(string $raw): ?int
{
    $value = strtolower(trim($raw));
    if ($value === '' || $value === 'all' || $value === '0') {
        return null;
    }
    if (!ctype_digit($value)) {
        return null;
    }
    return max(1, (int) $value);
}

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

/**
 * @param string[] $requiredFiles
 * @return string[]
 */
function validateZipHasRequiredFiles(string $zipPath, array $requiredFiles): array
{
    $missing = [];
    if (!is_file($zipPath) || !class_exists('ZipArchive')) {
        return $requiredFiles;
    }
    $zip = new ZipArchive();
    if ($zip->open($zipPath) !== true) {
        return $requiredFiles;
    }

    $present = [];
    for ($i = 0; $i < $zip->numFiles; $i++) {
        $name = $zip->getNameIndex($i);
        if (!is_string($name) || $name === '') {
            continue;
        }
        $parts = explode('/', str_replace('\\', '/', $name));
        $basename = end($parts);
        if (is_string($basename) && $basename !== '') {
            $present[$basename] = true;
        }
    }
    $zip->close();

    foreach ($requiredFiles as $requiredFile) {
        if (!isset($present[$requiredFile])) {
            $missing[] = $requiredFile;
        }
    }
    return $missing;
}
