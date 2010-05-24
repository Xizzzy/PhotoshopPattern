<?php

error_reporting (E_ALL);
set_time_limit (0);

// Timer start
$mtime = explode (' ', microtime ());
$tstart = $mtime[1] + $mtime[0];

// caching generated files here
$cache = './pattern/';

if((!isset($_GET['baseline_height']) || !is_numeric($_GET['baseline_height']) || $_GET['baseline_height'] > 50 || $_GET['baseline_height'] < 1)
|| (!isset($_GET['modul_width']) || !is_numeric($_GET['modul_width']) || $_GET['modul_width'] > 300 || $_GET['modul_width'] < 0)
|| (!isset($_GET['modul_baselines']) || !is_numeric($_GET['modul_baselines']) || $_GET['modul_baselines'] > 10 || $_GET['modul_baselines'] < 0)
|| (!isset($_GET['margin']) || !is_numeric($_GET['margin']) || $_GET['margin'] > 80 || $_GET['margin'] < 0)
|| (!isset($_GET['format']) || $_GET['format'] != 'photoshop'))
{
	header ('HTTP/1.1 404 Not Found');
	exit ();
}

require ('./photoshop_pattern.class.php');

$total_width = $_GET['modul_width'] + $_GET['margin'];
$total_baselines = $_GET['modul_baselines'] + 1;
$total_height = $_GET['baseline_height'] * $total_baselines;
$modul_height = $_GET['baseline_height'] * $_GET['modul_baselines'];

$pat = new PhotoshopPattern ();

// New pattern name "ModularGrid [xx-xx-xx-xx]"
$grid = $pat->CreatePattern ($total_width, $total_height, 'ModularGrid ['.$_GET['baseline_height'].'-'.$_GET['modul_width'].'-'.$_GET['modul_baselines'].'-'.$_GET['margin'].']');

$sides  = $pat->ColorAllocate ($grid, 'fff2f2');
$modul  = $pat->ColorAllocate ($grid, 'ffe6e6');
$corner = $pat->ColorAllocate ($grid, 'ffffff');
$lines  = $pat->ColorAllocate ($grid, 'e8bebe');

$pat->DrawRect ($grid, 0, 0, $total_width, $total_height, $sides);
$pat->DrawRect ($grid, 0, 0, $_GET['modul_width'], $modul_height, $modul);
$pat->DrawRect ($grid, $_GET['modul_width'], $modul_height, $_GET['margin'], $_GET['baseline_height'], $corner);

for ($i = 1; $i <= $total_baselines; $i++)
{
	$pat->DrawRect ($grid, 0, $_GET['baseline_height'] * $i - 1, $total_width, 1, $lines);
}

$logname = $pattern_file = $_GET['baseline_height'].'-'
													.$_GET['modul_width'].'-'
													.$_GET['modul_baselines'].'-'
													.$_GET['margin'].'-'
													.$_GET['format'];
$pattern_file = $cache.$pattern_file;
$pattern_file_nogzip = $pattern_file.'.nogzip.pat';
$pattern_file .= '.pat';

// Generate and save to cache
$pat->GetPatternFile ($pattern_file_nogzip);
$pattern_data = $pat->GetPatternFile ();

header ('Content-Type: application/octet-stream');

// Compress (gzip) and save to cache
$pattern_gzip = gzencode ($pattern_data, 9);
file_put_contents ($pattern_file, $pattern_gzip);

// check Accept-Encoding
if(isset($_GET['encode']) && $_GET['encode'] == "gzip")
{
	header ('Content-Encoding: gzip');
	echo $pattern_gzip;
}
else
{
	echo $pattern_data;
}

#passthru ('gzip -cnk9 '.$pattern_file_nogzip.' > '.$pattern_file);
#readfile ($pattern_file);

// Timer end
$mtime = explode(' ', microtime());
$tend = $mtime[1] + $mtime[0];

file_put_contents ('pattern.log', $logname.' '.($tend - $tstart).' '.date ('Y-m-d\TH:i:sP')."\n", FILE_APPEND);

?>