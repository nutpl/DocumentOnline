<?php

/**
 * Deployment preflight: source files must never contain unresolved Git merge
 * conflict markers.  The marker strings are assembled to keep this test file
 * itself from being reported as a false positive by external scanners.
 */
$projectRoot = dirname(__DIR__);
$excludedDirectories = ['.git', 'vendor', 'node_modules', 'uploads', 'generated'];
$sourceExtensions = ['php', 'sql', 'js', 'css', 'json', 'html', 'md', 'yml', 'yaml'];
$openingMarker = str_repeat('<', 7);
$dividerMarker = str_repeat('=', 7);
$closingMarker = str_repeat('>', 7);
$failures = [];

$iterator = new RecursiveIteratorIterator(
    new RecursiveDirectoryIterator($projectRoot, FilesystemIterator::SKIP_DOTS)
);

foreach ($iterator as $file) {
    $relativePath = str_replace('\\', '/', substr($file->getPathname(), strlen($projectRoot) + 1));
    $pathParts = explode('/', $relativePath);

    if (array_intersect($excludedDirectories, $pathParts)) {
        continue;
    }

    if (!in_array(strtolower($file->getExtension()), $sourceExtensions, true)) {
        continue;
    }

    $lines = file($file->getPathname());
    if ($lines === false) {
        $failures[] = $relativePath . ': cannot read file';
        continue;
    }

    foreach ($lines as $lineNumber => $line) {
        $line = rtrim($line, "\r\n");
        $isOpeningMarker = str_starts_with($line, $openingMarker . ' ');
        $isDividerMarker = $line === $dividerMarker;
        $isClosingMarker = str_starts_with($line, $closingMarker . ' ');

        if ($isOpeningMarker || $isDividerMarker || $isClosingMarker) {
            $failures[] = $relativePath . ':' . ($lineNumber + 1);
        }
    }
}

if ($failures !== []) {
    echo "Unresolved merge-conflict markers found:\n" . implode("\n", $failures) . PHP_EOL;
    exit(1);
}

echo "merge_conflict_marker_check=passed\n";
