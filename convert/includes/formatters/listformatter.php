<?php
	//日本語UTF8

	require_once('formatter.php');

	/*
		A list is either a series of '  - ' or '  * ' prefixed lines
		
		FIXME: I assume only single nested lists are used. If you nest deeper, you probably
		need to question why you're using a listing instead of a normal section/paragraph
		structure to explain your data.
	*/
	class ListFormatter extends Formatter
	{
		function format(&$text)
		{
			$inlist=false;
			$innestedlist=false;

			$enum = "enumerate";
			$item = "itemize";
			$type="";
			$bound="";

			for($line = 0; $line<count($text); $line++)
			{
				$isitem = (preg_match("/^  \* /", $text[$line])>0);
				$isenum = (preg_match("/^  - /", $text[$line])>0);

				$issubitem = (preg_match("/^    \* /", $text[$line])>0);
				$issubenum = (preg_match("/^    - /", $text[$line])>0);

				$issub = $issubitem || $issubenum;

				// what type of list are we in
				if($isitem || $issubitem) { $type = $item; }
				elseif($isenum || $issubenum) { $type = $enum; }
				else { $type=""; }
				
				// first list item?
				if($type!="" && !$inlist && !$issub) {
					$inlist = true;
					$bound = $type;
					$text[$line] = '\\begin{'.$type.'}'."\n" . $this->cleanup_it($text[$line], $issub); }
				
				// first sublist item?
				elseif($type!="" && !$innestedlist && $issub) {
					$innestedlist = true;
					$bound = $type;
					$text[$line] = '\\begin{'.$type.'}'."\n" . $this->cleanup_it($text[$line], $issub); }
								
				// nth list item
				elseif($type!="" && $inlist) {
					$text[$line] = $this->cleanup_it($text[$line], $issub);
					// if no longer nested, close off nested list too
					if($innestedlist  && !$issub) {
						$innestedlist = false;
						$text[$line] = '\\end{'.$bound.'}'."\n" . $text[$line]; }			
				}
				
				// not a list item
				elseif($type=="" && $inlist) {
					// shortcut: empty line should not interrupt
//					if($text[$line]=="\n") { $text[$line]=""; continue; }
					$inlist = false;
					$text[$line] = '\\end{'.$bound.'}'."\n" . $text[$line];
					$bound = ""; }
			}
		}
		
		function cleanup_it($string, $issub) 
		{
			return '\\item ' . substr($string, ($issub? 6 : 4));
		}
	}
?>