<?php
$text = file_get_contents('input_debug.txt');

// Simulate existing logic
$blocks_existing = preg_split('/\n\s*\n/', trim($text), -1, PREG_SPLIT_NO_EMPTY);
echo "Existing Logic Count: " . count($blocks_existing) . "\n";

// Debug: print lengths of blocks
foreach ($blocks_existing as $i => $block) {
    if (strlen(trim($block)) < 20) {
        echo "Block $i (Length " . strlen(trim($block)) . "): " . trim($block) . "\n";
    }
}

// Proposed Logic: Filter out blocks that are just separators or too short
$blocks_new = array_filter($blocks_existing, function ($b) {
    $trimmed = trim($b);
    // Ignore if it's just '=====' or shorter than 10 chars without digits?
    // User input shows some blocks are just IDs like '1767773097000' (13 chars)
    // Separators are usually '===='
    if (preg_match('/^=+$/', $trimmed))
        return false;
    return strlen($trimmed) > 0;
});

echo "New Logic Count: " . count($blocks_new) . "\n";
?>