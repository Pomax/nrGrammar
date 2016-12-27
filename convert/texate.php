<?php
	ini_set('error_reporting', E_ALL);
	ini_set('display_errors', 1);
	
	//日本語UTF8

	function showmem() { print("MEMORY: ".memory_get_usage ()."\n"); }

	/**
	 * PDF spinning script for the nihongoresources book.
	 * This script takes the dokuwiki text files and runs them through
	 * a series of text replacement operations to yield valid LaTeX code,
	 * after which this code is fed to XeLaTeX which generates the final
	 * PDF file. XeLaTeX is used because it is unicode and font aware,
	 * rather than needing the crazy TeX font system and weird unicode
	 * forcing packages.
	 *
	 * This code was written using a 1440x900 widescreen monitor,
	 * at full screen (I only wrap text for easthetics).
	 */

	// the filename that will be used all over the place
	$filename = "texated";
	
	// if false, creates a production copy
	$draft_copy=true;
	// check if we need to run latex after the conversion pass
	$run_latex=false;
	// check if we need to convert dokuwiki to TeX
	$convert_to_tex=true;
	// check if we need to delete the needed-while-running-xelatex files
	$unlink=true;
	// check if we need to save files for all the different steps
	$stepthrough=false;
	// determines whether or not quotes will be replaced with proper ('curly') quotes
	$quote_replace=true;
	// set up the default preface as being in the "general" dir
	$preface="general";
	if($argc>1) {
		for($a=1;$a<count($argv);$a++) {
			if($argv[$a]=="--runlatex") { $run_latex=true; }
			elseif($argv[$a]=="--nounlink") { $unlink=false; }
			elseif($argv[$a]=="--noquote") { $quote_replace=false; }
			elseif($argv[$a]=="--noquotes") { $quote_replace=false; }
			elseif($argv[$a]=="--preface") { $preface=$argv[$a+1]; $a++; }
			elseif($argv[$a]=="--outfile") { $filename=$argv[$a+1]; $a++; }
			elseif($argv[$a]=="--stepthrough") { $stepthrough=true; }
			elseif($argv[$a]=="--noconvert") { $convert_to_tex=false; }
			elseif($argv[$a]=="--nodraft") { $draft_copy=false;}}}

	// and a quick general purpose println function
	function println($string) { print($string . "\n"); }

	// include file related and text replacement functions
	include_once("includes/filefunctions.php");
	include_once("includes/replacements.php");
	require_once("includes/regexp.php");

// ------------------------------------------------------------------
//  main program code
// ------------------------------------------------------------------

	$scriptstart = time();

	if($convert_to_tex)
	{
		// print a different message depending on whether we're only forming the .tex file, or also compiling it to pdf
		if($run_latex) { 
			println("Running dokuwiki-to-(Xe(La))TeX conversion and compilation...");
			// remove old files
			if(is_file($filename.".log")) @unlink($filename.".log");
			if(is_file($filename.".pdf")) @unlink($filename.".pdf");
			if(is_file($filename.".tex")) @unlink($filename.".tex"); }
		else { println("Running dokuwiki-to-(Xe(La))TeX conversion only..."); }

		// the content array. we need to make sure to strip license text (this is always the first four lines in each file)
		$text = array();

		// set up the filenames to run through for chapter content
		$chapters = array("syntax",
						 "verb grammar",
						 "more verb grammar",
						 "particles",
						 "counters",
						 "language patterns");
 		//
		// Get chapter text
		//
		// note that in my dokuwiki, each chapter starts at line four.
		// (the first three lines are [blank], disclaimer, and [blank].)
		//
		// The chapter's title is whatever the dokuwiki markup indicates as top-level section
		// (see replacements script)
		//
		foreach($chapters as $chapter) {
			$lines = file(get_filename($chapter));
			$text[] = "\n";
			$text = array_merge($text, array_slice($lines,3)); }


		// set up the filenames to run through for appendix content (with separate titles, unlike for the chapters)
		$appendices = array("conjugation",
							"set phrases");
		$appendixnames = array("conjugation"=>"Conjugation Schemes",
							"set phrases"=>"Set Phrases");

		// appendices need a bit of "midamble" to make them properly titled
		$text[] = "\n\n\\clearpage\n\n";
		$text[] = "\\appendix\n";
		$text[] = "\\appendixpage\n";
		$text[] = "\\addappheadtotoc\n\n";
		$text[] = "\\renewcommand{\\chaptername}{Appendix }\n";
		foreach($appendices as $appendix) {
			$headerinfo = array("\\nameheaders{".$appendixnames[$appendix]."}\n");
			$lines = file(get_filename($appendix));
			// filter out ToC entries for the "set phrases" page, since we don't want the phrases listed
			if($appendix=="set phrases") {
				for($l=0;$l<count($lines);$l++) {
					$lines[$l] = preg_replace("/^==== (.+) ====/u","\section*{"."$1"."}",$lines[$l]); }}
			$text[] = "\n";
			$text = array_merge($text, array_slice($lines,4,1), $headerinfo, array_slice($lines,5)); }

		// convert the text from dokuwiki to tex format. This is a pass-by-reference replacement process
		run_replacements($text);

		// ------------------
		//  final clean up
		// ------------------

		// condense text where necessary (at the moment this means only use \n as newline code, and reduce consecutive vspaces)
		println("Cleaning up data...");
		$condensed = implode("",$text);
		unset($text);
		// uniform newlining
		$condensed = str_replace("\r\n","\n",$condensed);
		$condensed = str_replace("\r","\n",$condensed);
		// pruning too much implicit vertical space:
		$condensed = str_replace("\\end{itemize}\n\\vspace{1em}\n","\\end{itemize}\n",$condensed);
		$condensed = preg_replace("/vspace{([^}]+)}\s+\W+vspace{[^}]+}/","vspace{".'$1'."}\n",$condensed);
		// remove space before a section
		$condensed = preg_replace("/vspace{[^}]+}\s+\W((sub)*)section([^\n]+)/",'$1'."section".'$3'."\n",$condensed);
		
		// remove space after a section - mostly relevant for section + tabling
		// FIXME: this should be in some kind of section parsing instead, for optimisation
		$condensed = preg_replace("/(.)((sub)*)section{([^\n]+)\s+__IMMEDIATE_TABLE__\s+\W+vspace{[^}]+}/","\n\\Needspace{6\\baselineskip}\n".'$1$2'."section{".'$4'."\n",$condensed);
		// turning //text// into \textit{text}
		$condensed = preg_replace("/\/\/([^\/]+)\/\//u", "\\textit{".'$1'."}", $condensed);
		println("Done clean-up.\n");
		unset($content);
		
		// ------------------
		//  write TeX file
		// ------------------
		
		
		// finally, do quote replacement - this is an expensive, but essential operation
		if($quote_replace) {
			print("[".date("H:i:s")."] - Rewriting typewriter quotes to curly quotes in content...");
			require_once("includes/formatters/quoteformatter.php");
			$start = time();
			$quote_formatter = new QuoteFormatter();
			$quote_formatter->format($condensed);
			unset($quote_formatter);
			$end = time();
			println(" done (took ".($end-$start)."s)"); }

		// write data to file
		println("Writing $filename.tex...");
		$fh = fopen($filename.".tex","w");
		fwrite($fh, chr(239) . chr(187) . chr(191));	// UTF-8 Byte Order Mark
		fwrite($fh, get_preamble($draft_copy));
		fwrite($fh, $condensed);
		unset($condensed);
		fwrite($fh, get_postamble());
		fclose($fh);
	}

// ------------------
//  Run XeLaTeX
// ------------------

	if($run_latex) {
		include('includes/xelatex.php');
		print("\n");
		run_xelatex($filename); }


// ------------------
//  Done!
// ------------------

	$scriptend = time();
	println("Script finished (total runtime: ".($scriptend-$scriptstart)."s)");
?>	