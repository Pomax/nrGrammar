<?php
	//日本語UTF8

	// run all dokuwiki->tex replacements
	function run_replacements(&$text)
	{
		global $stepthrough;
		$start = time();
		$ops = 9;
		$op = 0;
		general_replacements($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_headings($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_idx($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_ruby($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_tabling($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_lists($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_examples($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_images($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		replace_verticalruby($text, ++$op, $ops);
		if($stepthrough) dump($text,"replace $op.txt");
		$end = time();
		println("Conversion steps took ".($end-$start)."s.");
	}

	// replace percentage signs with TeX versions!
	function general_replacements(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Performing general replacements...");
		$start = time();
		for($line = 0; $line<count($text); $line++) {
			// TeX doesn't like these characters unescaped
			$text[$line] = str_replace("%", "\\%", $text[$line]);
			$text[$line] = str_replace("#", "\\#", $text[$line]);
			$text[$line] = str_replace("$", "\\$", $text[$line]);
			// Superscript a number's 'th', 'nd', and 'rd'
			$text[$line] = preg_replace("/(\d+|\.\.\.)th/", "$1\\textsuperscript{th}", $text[$line]);
			$text[$line] = preg_replace("/(\d+)nd/", "$1\\textsuperscript{nd}", $text[$line]);
			$text[$line] = preg_replace("/(\d+)rd/", "$1\\textsuperscript{rd}", $text[$line]);
			// Replace ellipses
			$text[$line] = str_replace("...", "…", $text[$line]);
		}
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}

	// replace headings with chapter/section markup
	function replace_headings(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Converting headings...");
		$start = time();
		for($line = 0; $line<count($text); $line++) {
			$text[$line] = preg_replace("/====== (.+) ======/u", "\\chapter{"."$1".'}', $text[$line]);
			$text[$line] = preg_replace("/===== (.+) =====/u", "\\section{"."$1".'}', $text[$line]);
			$text[$line] = preg_replace("/==== (.+) ====/u", "\\subsection{"."$1".'}', $text[$line]);
			$text[$line] = preg_replace("/=== (.+) ===/u", "\\subsubsection{"."$1".'}', $text[$line]);
			$text[$line] = preg_replace("/== (.+) ==/u", "\\paragraph{"."$1".'}', $text[$line]); }
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
	
	// replace ruby
	function replace_ruby(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Converting ruby markup...");
		$start = time();
		$kanji = "[\x{4E00}-\x{9FFF}\x{3005}\x{30F6}]+";
		$kana = "[\x{3040}-\x{30FF}]*";
		
		$search = "/(".$kanji.")\((".$kana.")\)/u";
		$replace = "\\ruby".'{'."$1".'}{'."$2".'}';
		
		for($line = 0; $line<count($text); $line++) {
			$text[$line] = preg_replace($search, $replace, $text[$line]); }
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
	
	// replace the example phrase text formatting with something that TeX understands
	function replace_examples(&$text, $op, $ops)
	{
		include("includes/formatters/exampleformatter.php");
		print("[".date("H:i:s")."] - [$op/$ops] Converting examples...");
		$start = time();
		$formatter = new ExampleFormatter();
		$formatter->format($text);
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
	
	// replace tabling markup
	function replace_tabling(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Converting tabling...");
		$start = time();
		// because it's a lot of code and it would clutter, table replacement uses its own class
		include("includes/formatters/tableformatter.php");
		$formatter = new TableFormatter();
		$formatter->format($text);
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
	
	// replace numbered and itemised lists
	function replace_lists(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Converting lists...");
		$start = time();
		include("includes/formatters/listformatter.php");
		$formatter = new ListFormatter();
		$formatter->format($text);
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
	
	// replace indexing and glossary markup
	function replace_idx(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Rewriting index and glossary links...");
		$start = time();
		include("includes/formatters/idxglsformatter.php");
		$formatter = new IdxGlsFormatter();
		$formatter->format($text);
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
	
	// replace image links
	function replace_images(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Converting image links...");
		$start = time();
		
		//{{:gyousou.jpg|}}
		$search = "/{{([^:]*):([^|]+)\|([^}]*)}}/u";
		$replace = "\n\\vspace{0.5em}\n\\begin{center}\n\\fbox{\\includegraphics{".'../../data/media/'."$2}}\\nopagebreak[4]\\par {\\smaller \\smaller $3}\\end{center}\n";
		
		for($line = 0; $line<count($text); $line++) { 
			$text[$line] = preg_replace($search, $replace, $text[$line]); }
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
	
	// replace ruby in vertical macro to vruby
	function replace_verticalruby(&$text, $op, $ops)
	{
		print("[".date("H:i:s")."] - [$op/$ops] Setting up vertical text (and correcting ruby to vruby)...");
		$start = time();
		
		$invertical = false;
		for($line = 0; $line<count($text); $line++) {
			if(strpos($text[$line],"<begin vertical")!==false) {
				$invertical = true;
				$text[$line] = preg_replace("/<begin vertical ([^>]+)>/","\\vertical".'$1'."{",$text[$line]); }
			if($invertical) { $text[$line] = str_replace("\\ruby","\\vruby",$text[$line]); }
			if(strpos($text[$line],"<end vertical>")!==false) {
				$invertical = false;
				$text[$line] = str_replace("<end vertical>","}",$text[$line]); }}
		$end = time();
		println(" done (took ".($end-$start)."s)");
	}
?>