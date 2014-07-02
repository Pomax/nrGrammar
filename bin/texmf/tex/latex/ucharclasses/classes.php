<?php

/*
$line:
	split on \s+;\s+
	check first part for ..
		if exists
			hex4..hex4 range
		if not
			hex4 numeral
	check second part	
		script name
*/

print("% based on http://unicode.org/Public/UNIDATA/Scripts.txt\n\n");

$groups = array();

function add(&$array, $cat, $val)
{
	$array[$cat][] = hexdec($val);
}

function add_series(&$array, $cat, $start, $end)
{
	$startdec = hexdec($start);
	$enddec = hexdec($end);
	for($i=$startdec; $i<=$enddec; $i++) { add($array, $cat, dechex($i)); }
}


$data = file("classes.txt");

foreach($data as $line)
{
	$split = preg_split("/\s+;\s+/",trim($line));
	$cat = str_replace("_","",$split[1]);
	$nums = $split[0];
	if(strpos($nums, "..")!==false) {
		$anums = preg_split("/\.\./",$nums);
		add_series($groups,$cat,$anums[0],$anums[1]); }
	else { add($groups,$cat,$nums); }
}

// compact
$cgroups = array();
foreach($groups as $cat=>$list)
{
	$ranges = array();
	$start = $list[0];
	for($i=1; $i<count($list); $i++) {
		if($list[$i] != $list[$i-1] + 1) {
			// $i is no longer sequential
			$end = $list[$i-1];
			
			$ranges[] = "{".$start."}".
						($start==$end ? "{".$start."}" : "{".$end."}");
			// start a new range from this position
			$start = $list[$i]; }}
	// record the last range
	$ranges[] = "{".$start."}{".$list[count($list)-1]."}"; 	
	
	unset($groups[$cat]);
	$cgroups[$cat]=$ranges;
}

// texify
foreach($cgroups as $cat=>$col)
{
	print("\\newcommand{\\setScript$cat"."Transitions}[2]{%\n");
	foreach($col as $range) { print("\\setScriptTransitions{#1}{#2}$range\n"); }
	print("}\n");
}

?>