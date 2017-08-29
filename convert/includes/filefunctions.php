<?php
	//日本語UTF8

	// we assume we're executing from [dokuwikilocation]/bin/tex/
	$locale = "en-GB";
	$docloc = "../../data/pages/$locale/";
	$docext = ".txt";

	// dump data to file
	function dump(&$data, $file) {
		$fh = fopen($file,"w");
		fwrite($fh, is_array($data)? implode("",$data) : $data);
		fclose($fh); }

	// form a filename
	function get_filename($chapter) {
		global $docloc, $docext;
		return str_replace(" ","_", $docloc . $chapter . $docext); }

	// get preface filename
	function get_preface($preface) {
		global $docloc, $docext, $quote_replace;
		return str_replace(" ","_", $docloc . "preface/" . $preface . $docext);
	}

	// get the LaTeX preamble from file
	function get_preamble($draft_copy) {
		$text = file("includes/xelatex/preamble.txt");
		for($l=0;$l<count($text);$l++) {
			if(strpos($text[$l],"__GLOSSARY__")!==false) { $text[$l] = str_replace("__GLOSSARY__",make_glossary(),$text[$l]); }
			elseif(strpos($text[$l],"__ACKNOWLEDGEMENTS__")!==false) { $text[$l] = str_replace("__ACKNOWLEDGEMENTS__",get_acknowledgements_text(),$text[$l]); }
			elseif(strpos($text[$l],"__PREFACE__")!==false) { $text[$l] = str_replace("__PREFACE__",get_preface_text(),$text[$l]); }
			elseif(strpos($text[$l],"__DRAFT_OR_NOT__")!==false) {
				if($draft_copy) { $text[$l] = str_replace("__DRAFT_OR_NOT__",'\newcommand{\draft}{\true}',$text[$l]); }
				else { $text[$l] = str_replace("__DRAFT_OR_NOT__",'\newcommand{\draft}{\false}',$text[$l]); }}}
		return implode("",$text);
	}

	// get the LaTeX postamble from file
	function get_postamble() { return implode("",file("includes/xelatex/postamble.txt")); }

	// form the glossary, based on the glossary.txt file from the dokuwiki
	function make_glossary()
	{
		$glossary = array();
		$lines = file(get_filename("glossary"));
		$lines = array_slice($lines,3);
		$terms = array();
		$term = "";
		$description = "";
		foreach($lines as $line) {
			if(preg_match("/^===== /",$line)) {
				if($term!="") { $terms[$term] = str_replace("\n"," \\\\ ",str_replace("\n\n","\n",trim($description))); }
				$term = trim(str_replace("=","",$line));
				$description = ""; }
			else { $description .= $line; }}
		if($term!="") { $terms[$term] = str_replace("\n"," \\\\ ",str_replace("\n\n","\n",trim($description))); }

		// do quote replacement
		global $quote_replace;
		if($quote_replace) {
			print("[".date("H:i:s")."] - Rewriting typewriter quotes to curly quotes in glosterms...");
			require_once("includes/formatters/quoteformatter.php");
			$start = time();
			$quote_formatter = new QuoteFormatter();
			foreach($terms as $term => $description) {
				$quote_formatter->format($description);
				$glossary[] = "\\newglossaryentry".'{'.$term.'}{'."name=".'{'.$term.'}'.", description=".'{'.$description.'}}'."\n"; }
			unset($quote_formatter);
			$end = time();
			println(" done (took ".($end-$start)."s)"); }

		// no quote replacement
		else {
			foreach($terms as $term => $description) {
				$glossary[] = "\\newglossaryentry".'{'.$term.'}{'."name=".'{'.$term.'}'.", description=".'{'.$description.'}}'."\n"; }
		}

		$text = implode("",$glossary);
		unset($glossary);
		return $text;
	}

		// acknowledgements
	function get_acknowledgements_text()
	{
		global $quote_replace;

		$lines = file(get_filename("acknowledgements"));
		// quick replace to prevent indexing
		for($ti=0;$ti<count($lines);$ti++) {
			$lines[$ti] = str_replace("====== Acknowledgements ======","\chapter*{Acknowledgements}",$lines[$ti]); }
		$text = implode("",array_slice($lines,3));
		unset($lines);

		if($quote_replace) {
			print("[".date("H:i:s")."] - Rewriting typewriter quotes to curly quotes in acknowledgements...");
			require_once("includes/formatters/quoteformatter.php");
			$start = time();
			$quote_formatter = new QuoteFormatter();
			$quote_formatter->format($text);
			unset($quote_formatter);
			$end = time();
			println(" done (took ".($end-$start)."s)"); }

		return $text;
	}

	// preface
	function get_preface_text()
	{
		global $preface, $quote_replace;

		$lines = file(get_preface($preface));
		// quick replace to prevent indexing
		for($ti=0;$ti<count($lines);$ti++) {
			$lines[$ti] = str_replace("====== Preface ======","\chapter*{Preface}",$lines[$ti]); }
		$text = implode("",array_slice($lines,3));
		unset($lines);

		// do quote replacement
		if($quote_replace) {
			print("[".date("H:i:s")."] - Rewriting typewriter quotes to curly quotes in preface...");
			require_once("includes/formatters/quoteformatter.php");
			$start = time();
			$quote_formatter = new QuoteFormatter();
			$quote_formatter->format($text);
			unset($quote_formatter);
			$end = time();
			println(" done (took ".($end-$start)."s)"); }

		// and return...
		return $text;
	}
?>