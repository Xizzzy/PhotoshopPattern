<?php

// Timer start
$mtime = explode(' ', microtime());
$tstart = $mtime[1] + $mtime[0];

require('../photoshop_pattern.class.php');

/*
 * Modular grid generator for modulargrid.org
 * Initial values
 */

$module_width = 60;     // px
$gutter = 24;           // px
$baseline = 22;         // px
$module_baselines = 4;  // Num of baselines in module

// Calculate sizes
$total_width = $module_width + $gutter;
$total_baselines = $module_baselines + 1;
$total_height = $baseline * $total_baselines;
$module_height = $baseline * $module_baselines;

$pattern = new PhotoshopPattern ();

$module_sizes = $baseline . '-'
              . $gutter . '-'
              . $module_width . '-'
              . $module_baselines;

$grid = $pattern->CreatePattern($total_width, $total_height, 'ModularGrid [' . $module_sizes . ']', PHOTOSHOP_PATTERN_RGB);

// Define colors
$sides = $pattern->ColorAllocate($grid, 'f2050d', 12);
$module = $pattern->ColorAllocate($grid, 'f2050d', 30);
$corner = $pattern->ColorAllocate($grid, '00000000');
$lines = $pattern->ColorAllocate($grid, 'd9040b', 77);

// Draw grid
$pattern->DrawRect($grid, 0, 0, $total_width, $total_height, $sides);
$pattern->DrawRect($grid, 0, 0, $module_width, $module_height, $module);
$pattern->DrawRect($grid, $module_width, $module_height, $gutter, $baseline, $corner);

// Add lines
for ($i = 1; $i <= $total_baselines; $i++) {
    $pattern->DrawRect($grid, 0, $baseline * $i - 1, $total_width, 1, $lines);
}

// Generate pattern file
$pattern_data = $pattern->GetPatternFile('./ModularGrid [' . $module_sizes . '].pat');

// Timer end
$mtime = explode(' ', microtime());
$tend = $mtime[1] + $mtime[0];

echo 'Time: ' . ($tend - $tstart);
echo "\n";
echo 'Mem: ' . getNiceFileSize(memory_get_peak_usage());

function getNiceFileSize($bytes, $binaryPrefix = true)
{
    if ($binaryPrefix) {
        $unit = array('B', 'KiB', 'MiB', 'GiB', 'TiB', 'PiB');
        if ($bytes == 0) return '0 ' . $unit[0];
        return @round($bytes / pow(1024, ($i = (int) floor(log($bytes, 1024)))), 2) . ' ' . (isset($unit[$i]) ? $unit[$i] : 'B');
    } else {
        $unit = array('B', 'KB', 'MB', 'GB', 'TB', 'PB');
        if ($bytes == 0) return '0 ' . $unit[0];
        return @round($bytes / pow(1000, ($i = (int) floor(log($bytes, 1000)))), 2) . ' ' . (isset($unit[$i]) ? $unit[$i] : 'B');
    }
}
