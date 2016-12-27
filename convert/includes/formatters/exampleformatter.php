<?php
	//日本語UTF8
	require_once('formatter.php');

	/*
		An example line starts with two spaces, followed by not a * and not a - (which are list markers in dokuwiki)
		The idea is to wrap all examples in an example{} macro.
	*/
	class ExampleFormatter extends Formatter
	{
		function format(&$text)
		{
			$japanese = "\x{4E00}-\x{9FFF}\x{3000}-\x{303F}\x{30F6}\x{3040}-\x{30FF}";
			$valpat = "/^  [\w\"\\\[\(\'$japanese]/u";
			// generalised validation pattern
			$valpat = "/^  /u";
			
			$begin = '\vspace{0.4cm}'."\n".'\begin{exampleblock}'."\n";
			$end = '\end{exampleblock}'."\n".'\vspace{0.2cm}'."\n";
			$lb = " \\\\* \n";
			
			$indented=false;
			for($line = 0; $line<count($text); $line++)
			{
				$wasexclosed = (isset($text[$line-1]) && preg_match("/$end$/u",$text[$line-1])) ? true : false;
				$isex = preg_match($valpat, $text[$line]);
				$nextex = (isset($text[$line+1]) && ($text[$line+1]=="\n" || preg_match($valpat,$text[$line+1]))) ? true : false;

			
				// this is an example line (do we open, or open and close?)
				if($isex) {
					// previous line(s) was example line
					if($indented) {
						// next line is also an example line: only add linebreak
						if($nextex) {
							$text[$line] = str_replace("\n","",$this->cleanup_ex($text[$line])) . $lb; }
								
						// next line is not an example: close the block
						else {
							$indented = false;
							$text[$line] = $this->cleanup_ex($text[$line]) . $end; }}

					// previous line(s) was not an example line
					else {
						// next line is an example line: start block, leave open
						if($nextex) {
							// open, but don't close
							$indented=true;
							$text[$line] = $begin . str_replace("\n","",$this->cleanup_ex($text[$line])) . $lb; }
							
						// next line is not an example line: start block, and immediately close again
						else {
							// open and close
							$text[$line] = $begin . $this->cleanup_ex($text[$line]) . $end; }}
				}
				
				// this is not an example line, but the previous line was
				elseif($indented) {
					$indented = false;
					$text[$line] = $end . $text[$line]; }
			}
		}
		
		function cleanup_ex($string) 
		{
			return trim($string); 
		}
	}
?>