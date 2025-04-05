#!/usr/bin/env node

/**
 * MicroChaos Build Script
 *
 * This script compiles the modular version of MicroChaos into a single file for distribution.
 *
 * Usage: node build.js
 */

const fs = require("fs");
const path = require("path");

// Configuration
const outputDir = path.join(__dirname, "dist");
const outputFile = path.join(outputDir, "microchaos-cli.php");
const sources = {
  header: path.join(__dirname, "microchaos-cli.php"),
  components: [
    path.join(__dirname, "microchaos/core/thresholds.php"),
    path.join(__dirname, "microchaos/core/integration-logger.php"),
    path.join(__dirname, "microchaos/core/request-generator.php"),
    path.join(__dirname, "microchaos/core/resource-monitor.php"),
    path.join(__dirname, "microchaos/core/cache-analyzer.php"),
    path.join(__dirname, "microchaos/core/reporting-engine.php"),
    path.join(__dirname, "microchaos/core/commands.php"),
  ],
};

console.log("Building MicroChaos single-file distribution...");

// Create output directory if it doesn't exist
if (!fs.existsSync(outputDir)) {
  console.log(`Creating output directory: ${outputDir}`);
  fs.mkdirSync(outputDir, { recursive: true });
}

// Extract the header part of the main file (up to but not including the bootstrap loader)
const headerContents = fs.readFileSync(sources.header, "utf8");
const headerPattern = /^(.+?\/\/ Bootstrap MicroChaos components)/ms;
const headerMatches = headerContents.match(headerPattern);
const header = headerMatches ? headerMatches[1] : "";

if (!header) {
  console.error("Error: Could not extract header from main file.");
  process.exit(1);
}

// Start with the plugin header
let compiledCode = header + "\n\n";
compiledCode += `/**\n * COMPILED SINGLE-FILE VERSION\n * Generated on: ${new Date().toISOString()}\n * \n * This is an automatically generated file - DO NOT EDIT DIRECTLY\n * Make changes to the modular version and rebuild.\n */\n\n`;

// Collect all component classes
const classContents = [];
for (const componentFile of sources.components) {
  console.log(`Processing component: ${path.basename(componentFile)}`);
  const content = fs.readFileSync(componentFile, "utf8");

  // Remove PHP opening tags, prevent direct access blocks, etc.
  const cleanedContent = content
    .replace(/^<\?php/, "")
    .replace(/\/\/ Prevent direct access[\s\S]+?exit;\s*\}/m, "")
    .replace(/if \(!defined\('ABSPATH'\)[\s\S]+?exit;\s*\}/m, "")
    .replace(/if \(!defined\('WP_CLI'\)[\s\S]+?exit;\s*\}/m, "");

  // Extract the class declaration and all its content
  const classPattern = /class\s+([A-Za-z0-9_]+)[\s\S]+?^}/ms;
  const classMatches = cleanedContent.match(classPattern);

  if (classMatches && classMatches[0]) {
    classContents.push(classMatches[0]);
  } else {
    console.warn(
      `Warning: Could not extract class from ${path.basename(componentFile)}`
    );
  }
}

// Add component classes to compiled code
compiledCode += "if (defined('WP_CLI') && WP_CLI) {\n\n";
compiledCode += classContents.join("\n\n");
compiledCode += "\n\n";

// Add the WP-CLI command registration
compiledCode += "    // Register the MicroChaos WP-CLI command\n";
compiledCode +=
  "    WP_CLI::add_command('microchaos', 'MicroChaos_Commands');\n";
compiledCode += "}\n";

// Write the compiled code to the output file
console.log(`Writing compiled code to: ${outputFile}`);
fs.writeFileSync(outputFile, compiledCode);

// Verify the file was created successfully
if (fs.existsSync(outputFile)) {
  const stats = fs.statSync(outputFile);
  console.log(
    `Build successful! Single-file version created at: ${outputFile}`
  );
  console.log(`File size: ${(stats.size / 1024).toFixed(2)} KB`);
} else {
  console.error("Error: Failed to create output file.");
}
