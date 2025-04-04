<?php
/**
 * MicroChaos Build Script
 *
 * This script compiles the modular version of MicroChaos into a single file for distribution.
 *
 * Usage: php build.php
 */

// Configuration
$outputDir = __DIR__ . '/dist';
$outputFile = $outputDir . '/microchaos-cli.php';
$sources = [
    'header' => __DIR__ . '/microchaos-cli.php',
    'components' => [
        __DIR__ . '/microchaos/core/request-generator.php',
        __DIR__ . '/microchaos/core/resource-monitor.php',
        __DIR__ . '/microchaos/core/cache-analyzer.php',
        __DIR__ . '/microchaos/core/reporting-engine.php',
        __DIR__ . '/microchaos/core/commands.php',
    ]
];

echo "Building MicroChaos single-file distribution...\n";

// Create output directory if it doesn't exist
if (!is_dir($outputDir)) {
    echo "Creating output directory: $outputDir\n";
    mkdir($outputDir, 0755, true);
}

// Extract the header part of the main file (up to but not including the bootstrap loader)
$headerContents = file_get_contents($sources['header']);
$headerPattern = '/^(.+?\/\/ Bootstrap MicroChaos components)/ms';
preg_match($headerPattern, $headerContents, $matches);
$header = $matches[1] ?? '';

if (empty($header)) {
    die("Error: Could not extract header from main file.\n");
}

// Start with the plugin header
$compiledCode = $header . "\n\n";
$compiledCode .= "/**\n * COMPILED SINGLE-FILE VERSION\n * Generated on: " . date('Y-m-d H:i:s') . "\n * \n * This is an automatically generated file - DO NOT EDIT DIRECTLY\n * Make changes to the modular version and rebuild.\n */\n\n";

// Collect all component classes
$classContents = [];
foreach ($sources['components'] as $componentFile) {
    echo "Processing component: " . basename($componentFile) . "\n";
    $content = file_get_contents($componentFile);

    // Extract just the class definition (remove PHP tags, includes, etc.)
    $classPattern = '/class\s+([A-Za-z0-9_]+)[\s\n]*{(.+?)}\s*$/ms';
    preg_match($classPattern, $content, $matches);

    if (isset($matches[0])) {
        $classContents[] = $matches[0];
    } else {
        echo "Warning: Could not extract class from " . basename($componentFile) . "\n";
    }
}

// Add component classes to compiled code
$compiledCode .= "if (defined('WP_CLI') && WP_CLI) {\n\n";
$compiledCode .= implode("\n\n", $classContents);
$compiledCode .= "\n\n";

// Add the WP-CLI command registration
$compiledCode .= "    // Register the MicroChaos WP-CLI command\n";
$compiledCode .= "    WP_CLI::add_command('microchaos', 'MicroChaos_Commands');\n";
$compiledCode .= "}\n";

// Write the compiled code to the output file
echo "Writing compiled code to: $outputFile\n";
file_put_contents($outputFile, $compiledCode);

// Verify the file was created successfully
if (file_exists($outputFile)) {
    echo "Build successful! Single-file version created at: $outputFile\n";
    echo "File size: " . round(filesize($outputFile) / 1024, 2) . " KB\n";
} else {
    echo "Error: Failed to create output file.\n";
}
