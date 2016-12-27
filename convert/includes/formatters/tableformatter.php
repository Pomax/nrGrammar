<?php
	//日本語UTF8

	require_once('formatter.php');

	/**
	 * The table formatter turns tab delimited table data into LaTeX table format,
	 * using either the tabular or longtable package.
	 *
	 * I only use tab delimited tables on my dokuwiki, for which I use the tabtables plugin.
	 *
	 * Rather than trusting the tabling packages, this converter forces text wrapping itself,
	 * by assuming a few things.
	 *
	 *	1: text is assumed 12 points, with 15 point kerning
	 *	2: only the last column in a table should be wrapped
	 *	3: wrapping should only occur if the table is wider than the page
	 *
	 * Table width is estimated based on column widths in characters. If there is more
	 * than 70 characters worth of horizontal space used, wrapping will be applied.
	 *
	 */
	class TableFormatter extends Formatter
	{
		/**
		 * Tablify - runs through the base text, and replaces tab delimited table data
		 * with proper docuwiki table syntax 
		 */
		function format(&$text)
		{
			// tabling administration
			$table_position = -1;
			$in_block = false;
			$_table_data = array();
			$_empty_count = 0;

			// iterate through the data, line by line
			for($l=0; $l<count($text); $l++) {
				$line = $text[$l];

				// aggregate tabling lines (a tabling line either contains tabs, or is either empty after trimming)
				if(strpos($line,"\t")!==false || ($in_block && $line=="\n")) {
					$ntbt_lock = true;
					
					// set up table block if not aggregating yet
					if(!$in_block) {
						$in_block=true;
						$table_position=$l;
						$_table_data = array(); }

					// if empty line, is this the first or second consecutive empty line?
					// If the second, we need to finalise this table block
					if($line=="\n" && count($_table_data)>1) { $in_block = $this->finalise($_table_data, $table_position, $text); }

					// if we didn't just finalise, aggregate the data
					if($in_block) { $_table_data[]=$line; }}

				// last option: this was not a tabling line, but we have a filled table block that needs processing
				elseif($in_block) { $in_block = $this->finalise($_table_data, $table_position, $text); }
			}

			// In case the table was the last thing on the page, we still have a table block to process
			if($in_block) { $this->finalise($_table_data, $table_position, $text); }

		}  

		/**
		 * Gets the table data rewritten, then updates the modified container. returns false, so that
		 * the iteration knows we're no longer in table data aggregation mode.
		 */
		private function finalise(&$_table_data, $table_position, &$text)
		{
			// is the table preceded by just a section header?
			$immediate_table = false;
			for($i=$table_position-1;$i>=0;$i--) {
				$line = trim($text[$i]);
				if($line!="") {
					if(strpos($line, "section{")!==false) {
						$immediate_table = true; }
					break; }}
			
			// splice the data
			$pre = array_slice($text,0,$table_position);
			$replaced = $this->replace($_table_data, $immediate_table);
			$post = array_slice($text,$table_position+count($_table_data));
			$text = array_merge($pre, $replaced, $post);
		}

		/**
		 * this function does the actual syntax replacement
		 */
		private function replace($original, $immediate_table)
		{
			// our new table code
			$new_table_block = array();
			
			// table width starts at 1em for the table sides
			$new_table_width = 1;

			// do we need to use longtable? if the text is more than ... lines, we do
			$headered = isset($original[1]) && (trim($original[1])=="");
			$longtable_minimum = $headered? 8 : 6;
			$longtable = (count($original)>=$longtable_minimum) ? true : false;

			$indent = "\\hspace{0.5in} ";
			$noindent = "\\noindent ";

			$smaller = "\\smaller\n";
			$larger = "\\larger\n";

			$tablevspace = "\\vspace{0.5cm}\n";			// vertical spacing before and after the table
			$vseparator = "";							// vertical separator?
			$hseparator = "";							// horizontal separator?
			$headerseparator = "\\hline ";				// separator between header row and content row
			$tex_cell_separator = " & ";				// inter-cell latex code
			$alignmentindicator = "l";					// column alignments (in this case: left aligned)

			$ruby_strutting = "\\rule{0pt}{3.6ex}";		// strutting for row with japanese that has furigana
			$japanese_strutting = "\\rule{0pt}{2.4ex}";	// strutting for a row with japanese, but no furigana
			$normal_strutting = "\\rule{0pt}{2.4ex}";	// strutting for regular text
			
			$longtable_security = ($immediate_table) ? "" : "\\Needspace{6\\baselineskip}\n";	// required to ensure longtable doesn't yield an orphaned footer/header pair across two pags.
			
			$cells = count(split("\t",$original[0]));	// how many cells are we actually dealing with?
			$empty_line = str_repeat($tex_cell_separator,$cells-1) . " \\\\ \n";

			// replace the tabulation with TeX table syntax
			$has_headers=false;
			for($r=0; $r<count($original); $r++) { 
				$row = $original[$r];
				$new_table_block[$r] = (trim($row)=="") ?
					$empty_line : str_replace("\n", "\\\\ \n", str_replace("\t",$tex_cell_separator,$row));

				// administration: are there headers?
				if($r==1 && $new_table_block[1] == $empty_line) { $has_headers=true; }

				// make sure there's enough space for the rubied text in the table's cell, by using the rtruby macro instead
				$new_table_block[$r] = preg_replace("/\\\\ruby/","\\rtruby",$new_table_block[$r]); }

			// is the last line for this table empty? if so, clear it so that it doesn't become an empty table line.
			if($new_table_block[count($new_table_block)-1]==$empty_line) { 
				unset($new_table_block[count($new_table_block)-1]); }

			// guess width and determine if the table's too wide
			$guessed_table_width = $this->guess_table_width($new_table_block);
			$table_env = $longtable ? "longtable" : "mytabular";
			$width_cap = 25;
			$too_big = intval($guessed_table_width )>=intval($width_cap);

			// add strutting
			$japanese_pattern = "[\x{4E00}-\x{9FFF}\x{3005}\x{30F6}\x{3040}-\x{30FF}]+";
			for($r=0; $r<count($new_table_block); $r++) { 
				$row = &$new_table_block[$r];
				if($row==$empty_line) { $row = ($longtable ? "" : "\\hline\n"); continue; }
				if(strpos($row, "rtruby{")!==false) { $row = $ruby_strutting . $row; }
				elseif(preg_match("/$japanese_pattern/u", $row)>0) { $row = $japanese_strutting . $row; }
				else { $row = $normal_strutting . $row; }}

			// table defition should become {l $vseparator l $vseparator l ...} for as many cells as there are in a row for this table
			$border = "|";
			$def = $border . $vseparator . str_replace("  "," $vseparator ",str_repeat(" $alignmentindicator ",$cells)) . $vseparator . $border;

			// longtable
			if($longtable) { $this->setup_longtable($new_table_block, $has_headers, $headerseparator); }

			// we frame the table by giving it a top and bottom hline, and left and right vlines via table makeup definition
			$border = $longtable? "" : "\\hline \n";

			$begin="";
			$end="";
			
			// if this is a longtable, centering requires setting the LTleft and LTright values to \fill
			if($longtable) {
				$calign = "\\setlength\\LTleft\\fill\n\\setlength\\LTright\\fill\n";
				$nalign = "\\setlength\\LTleft\\parindent\n\\setlength\\LTright\\fill\n";
				$begin = $smaller . ($too_big ? $calign : $nalign) . $longtable_security . "\\begin{".$table_env."}[h]";
				$end = "\\end{".$table_env."}\n" . $larger;
			}
			// if this is a normal table instead, centering requires sticking it in a center environment
			else {
				$begincenter = "\\begin{center}\n";
				$endcenter = "\\end{center}\n";
				$begin = $smaller . ($too_big ? $begincenter : "") . "\\begin{".$table_env . ($too_big ? "centered" : "")."}";
				$end = "\\end{".$table_env . ($too_big ? "centered" : "")."}\n" . ($too_big ? $endcenter : "") . $larger; }

			// and form the final table
			$pre = array(($immediate_table? "__IMMEDIATE_TABLE__\n":""), $tablevspace . $begin . "{" . $def . "}", $border);
			$post = array($border, $end . $tablevspace . "\n");
			$table = array_merge($pre, $new_table_block, $post);
			
			// done
			return $table;
		}

		/**
		 *	What follows is experimental code, which is GUARANTEED to get the table width wrong.
		 *	However, as a crude estimator, it serves its purpose.
		 */
		private function setup_longtable(&$new_table_block, $has_headers, $headerseparator)
		{
			$longtable_block = array();
			if($has_headers) {
				// first header
				$longtable_block[] = "\\hline\n";
				$longtable_block[] = $new_table_block[0];
				$longtable_block[] = "$headerseparator\n";
				$longtable_block[] = "\\endfirsthead\n";
				// subsequent headers
				$longtable_block[] = "\\hline\n";
				$longtable_block[] = $new_table_block[0];
				$longtable_block[] = "$headerseparator\n";
				$longtable_block[] = "\\endhead\n"; }
			else {
				// first header (empty)
				$longtable_block[] = "$headerseparator\n";
				$longtable_block[] = "\\endfirsthead\n";
				// subsequent headers (also empty)
				$longtable_block[] = "$headerseparator\n";
				$longtable_block[] = "\\endhead\n"; }
			// initial footers
			$longtable_block[] = "\\hline\n";
			$longtable_block[] = "\\endfoot\n";
			// last footer
			$longtable_block[] = "\\hline\n";
			$longtable_block[] = "\\endlastfoot\n";	

			for($ltb=($has_headers?1:0); $ltb<count($new_table_block); $ltb++) {
				$longtable_block[] = $new_table_block[$ltb]; }

			$new_table_block = $longtable_block;
		}
		

		/**
		 *	What follows is experimental code, which is GUARANTEED to get the table width wrong.
		 *	However, as a crude estimator, it serves its purpose.
		 */
		private function guess_table_width(&$new_table_block)
		{
			$new_table_width = 0;
			$wdebug = false;
			$widths = array();
			if($wdebug) echo "Table Width Estimation\n";
			for($r=0; $r<count($new_table_block); $r++) {
				if($wdebug) echo "  checking ".$new_table_block[$r]."\n";
				$content = preg_split("/ & /u", $new_table_block[$r]);
				for($c=0; $c<count($content); $c++) {
					$width = 0;
					if($wdebug) echo "   cell: ".$content[$c]."\n";
					// strip out all the "you cannot see this" code, so that we're left with only real text
					$content[$c] = str_replace(array("\\hline"), "", $content[$c]);
					$content[$c] = str_replace("\\\\ ", "", $content[$c]);
					$content[$c] = preg_replace("/\\\\index{[^}]+}/", "", $content[$c]);
					$content[$c] = preg_replace("/\\\\rtruby{([^}]+)}{[^}]+}/", "$1", $content[$c]);
					if($wdebug) echo "    rewritten: ".$content[$c]."\n";
					$split = preg_split("//u",trim($content[$c]), -1, PREG_SPLIT_NO_EMPTY);
					if($wdebug) print_r($split);
					foreach($split as $char) {
						$w = $this->getCharWidth($char);
						if($wdebug) echo "      w ($char): $w\n";
						$width += $w; }
					if($wdebug) echo "    cell width: ".$width."\n";
					if(!isset($widths[$c]) || $widths[$c]<$width) { $widths[$c] = $width; }}}
			if($wdebug) echo "  widths: \n";
			if($wdebug) print_r($widths);
			for($w=0; $w<count($widths); $w++) { $new_table_width += $widths[$w]; }
			if($wdebug) echo "  table width: $new_table_width\n\n";
			
			// debug: add list of column sizes to table
			return $new_table_width;
		}
		
		
		/**
		 * gives a rough estimation of the width of a character, in em
		 */
		private function getCharWidth($char)
		{
			// using a uniform scale factor is easier than constantly changing all the values by hand
			$scale = 0.94;

			// is this an empty char? if so, no width;
			if($char=="") return 0;

			// if the character Japanese? if so, it's fixed width;
			elseif(RegExp::is_japanese($char)) return $scale * 0.91;

			// width ratios based on palatino linotype, then modified to 'correct' for TeX's reassembly
			elseif(strpos(",",$char)!==false) { return $scale * 0.1; }
			elseif(strpos(" ",$char)!==false) { return $scale * 0.28; }
			elseif(strpos("i[].'l",$char)!==false) { return $scale * 0.28; }
			elseif(strpos("j",$char)!==false) { return $scale * 0.3; }
			elseif(strpos(")(\/",$char)!==false) { return $scale * 0.3; }
			elseif(strpos('"',$char)!==false) { return $scale * 0.32; }
			elseif(strpos("opq",$char)!==false) { return $scale * 0.32; }
			elseif(strpos("f",$char)!==false) { return $scale * 0.35; }
			elseif(strpos("e",$char)!==false) { return $scale * 0.35; }
			elseif(strpos("trs",$char)!==false) { return $scale * 0.39; }
			elseif(strpos("zce",$char)!==false) { return $scale * 0.44; }
			elseif(strpos("6345{}789vg021",$char)!==false) { return $scale * 0.5; }
			elseif(strpos("kxyv",$char)!==false) { return $scale * 0.53; }
			elseif(strpos("hdnub",$char)!==false) { return $scale * 0.55; }
			elseif(strpos("w",$char)!==false) { return $scale * 0.72; }
			elseif(strpos("m",$char)!==false) { return $scale * 0.8; }

			// unknown character: we'll guess the 'average' palatino roman character width
			else return 0.45;
		}
	}
	
	/*
	
		I wrote this formatter before I knew how to do box evaluation in latex, so technically this entire formatter
		can be replaced with something that doesn't do the whole font profiling. Instead, it should create a wad of
		TeX code that basically sticks the table data in a box, and then evaluates the box's width. If it's greater 
		than the allowed width, it should unbox without indent space, otherwise it should unbox with indent.
		
		It's a really simple trick, and since this actually worked perfectly fine for me until I discovered that trick
		(thank you, XeTeX mailing list!) I didn't have a need to actually modify the code.
	
	*/
?>