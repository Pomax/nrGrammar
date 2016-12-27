<?php
	//日本語UTF8

	// auto-quoting script. This thing is expensive to run, but ESSENTIAL to proper typesetting of text.
	// Don't you fucking dare use typewriter quotes in your books, this is the 21st centurey, we all have
	// access to PROPER symbols. Put in the effort to make your product look good. Or not, use MY
	// effort to make your product look good. But MAKE IT LOOK GOOD!

	class QuoteFormatter extends Formatter
	{
		var $comma = "‚";
		var $apostrophe = "’";
		var $single_opener = "‘";
		var $single_closer = "’";
		var $double_opener = "“";
		var $double_closer = "”";


		// an apostrophe should not be converted if it represents an elision, which we'll know it is because
		// it's connecting two letters, instead of having spacing either before, or after.
		function is_elision(&$data, $pos) {
			if($pos>0 && $pos<count($data)-1) {
				$precond = preg_match("/[^\s(]/", $data[$pos-1])>0;		// not white space or an opening parenthesis
				$postcond = preg_match("/[^\s]/", $data[$pos+1])>0;		// not white space
				$elision = $precond && $postcond;
				return $elision; }}

		// input NEEDS TO BE a continuous string
		function format(&$text)
		{
			// preprocess for some special words
			$this->preprocess($text);

			// single glyphs for the win
			$data = preg_split("//u", $text);
			$text = "";

			// run initial replacements
			$single_open = false;
			$double_open = false;
			for($i=0; $i<count($data); $i++) {
				$curr = $data[$i];
				if($curr == '"') {
					// opening or closing?
					if(!$double_open) { $data[$i] = $this->double_opener; $double_open = true; }
					elseif($double_open) { $data[$i] = $this->double_closer; $double_open = false; }}
				// comma should be pretty.
				elseif($curr == ',') { $data[$i] = $this->comma; }
				else if($curr == "'") {
					// open, close, or apostrophe? (do nothing yet if apostrophe)
					if(!$single_open && !$this->is_elision($data, $i)) { $data[$i] = $this->single_opener; $single_open = true; }
					elseif($single_open) { $data[$i] = $this->single_closer; $single_open = false; }}}
			
			// second pass replaces all left-over apostrophes with U+2019 (’)
			$text = implode("",$data);
			$text = str_replace("'", $this->apostrophe, $text);

			// unset before "returning" text
			unset($data);
		}
		
		function preprocess(&$text)
		{
			// preprocessing for special words of which I don't know how to deal with quotecatching atm.
			$text = str_replace("o' clock", "o".$this->apostrophe." clock", $text);
		}
	}

?>