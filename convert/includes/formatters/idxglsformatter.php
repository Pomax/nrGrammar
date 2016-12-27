<?php
	//日本語UTF8

	require_once('formatter.php');
	
	/*
		This formatter takes care of index and glossary markup. This is not "native" to dokuwiki,
		and on grammar.nihongoresources.com uses a private plugin... which is a little broken and
		doesn't manage to filter out idx/gls references in everything (such as the ToC... I have to fix that)
	
		index: {idx:index:term}
		mapped index: {idx:index:term@list term[!nest term]}
		nested index: {idx:index:list term[!nest term]}
		
		if, in nesting, there is a nest term, that's the term to go with, not the list term.
		
		NOTE: @ indicates "as", so that A@B means "show A in the text, but index on the text 'B' instead".
		
		This is counter to how @ is interpreted in Latex, so beware~
	*/
	
	class IdxGlsFormatter extends Formatter
	{

		// replace indexing and glossary markup
		function format(&$text)
		{
			$idxpattern   = "/{idx:([^:]+):([^}]+)}/ue";
			$glosspattern = "/{gls:([^:]+):([^}]+)}/ue";

			$idxreplace   = '"\\index{".'  ."IdxGlsFormatter::idxclean('$2')".'."}".' ."IdxGlsFormatter::textclean('$2')";
			$glossreplace = '"\\glslink{".'."IdxGlsFormatter::idxclean('$2')".'."}{".'."IdxGlsFormatter::textclean('$2')".'."}"';

			for($line = 0; $line<count($text); $line++)
			{
				$content = $text[$line];
				$lineempty = trim($content)=="";
				
				$text[$line] = preg_replace($idxpattern, $idxreplace, $text[$line]);
				
				if(!$lineempty && trim($text[$line])=="") {
					print("\n\n--- ERROR! GOBBLED LINE $line AT IDX PARSE: $content\n");
					// revert
					$text[$line] = $content; }
				else { $content = $text[$line]; }

				$text[$line] = preg_replace($glosspattern, $glossreplace, $text[$line]);
				
				if(!$lineempty && trim($text[$line])=="") {
					print("\n\n--- ERROR! GOBBLED LINE $line AT GLOSS PARSE: $content\n"); 
					// revert
					$text[$line] = $content; 
					exit(0);
				}
			}
		}
		
		// helper function for filtering index entries
		static function idxclean($string)
		{
//			echo " (idx in: $string) ";

			$japanese = "[\x{4E00}-\x{9FFF}\x{3005}\x{30F6}\x{3040}-\x{30FF}]+";
			$is_mapped = (preg_match("/@/u",$string)>0);
			$is_nested = (preg_match("/!/u",$string)>0);

			// mapped intuitively (A@B = A indexed as B)
			if($is_mapped) {
				$terms = preg_split("/@/u",$string);
				$string = $terms[1];}

//			echo " (idx after map split: $string) ";

			// nested
			if($is_nested) {
				$cats = preg_split("/!/u",$string);
				for($c=0; $c<count($cats); $c++) {
					$cat = &$cats[$c];
//					echo " (split cat $c: $cat) ";
					if(preg_match("/$japanese/u",$cat)==0) {
						$cat = str_replace('\\"','',$cat);
						$cat = str_replace("\\'",'',$cat);
						$cat = ($c==0) ? IdxGlsFormatter::mb_ucfirst($cat) : mb_convert_case($cat, MB_CASE_LOWER, "UTF-8");
//						echo " (cat after rewrite: $cat) ";
					}
					else {
						$cat = IdxGlsFormatter::unrubify($cat); 
//						echo " (cat after unruby: $cat) ";
					}
				}
				// respect the English language at least a little
				$last = &$cats[count($cats)-1];
				if($last=="i") {$last = "I"; }
				// respect language names
				$last = str_replace("japan","Japan",$last);
				$last = str_replace("chin","Chin",$last);
				$last = str_replace("engl","Engl",$last);
				$string = implode("!",$cats); }

			// not mapped or nested, so just capitalise the first word (if it isn't japanese of course).
			if(preg_match("/^$japanese/u",$string)==0) {
				$string = str_replace('\\"','',$string);
				$string = str_replace("\\'",'',$string);
				$string = IdxGlsFormatter::mb_ucfirst($string);
//				echo " (idx !m!n, if: $string) ";
			}

			// not mapped, not nested, no western
			elseif(preg_match("/\w/u",$string)==0) {
				$string = IdxGlsFormatter::unrubify($string);
//				echo " (idx !m!n, !if: $string) ";
			}

			return $string;
		}
		
		// converts a string a la \ruby{base}{furi} into "furi (base)" 
		static function unrubify($string)
		{
			// if there is only japanese (=no western text), unravel
			if(preg_match("/[a-zA-Z]/u",$string)==0)
			{
				$kanji = "\x{4E00}-\x{9FFF}\x{3005}\x{30F6}";
				$kana = "\x{3040}-\x{30FF}";
				$block = "/([".$kanji.']+)\((['.$kana.']+)\)(['.$kana."]*)/u";
				preg_match_all($block, $string, $smatches, PREG_SET_ORDER);

				$pre = preg_split($block,$string);
				$pre = (count($pre)>0) ? $pre[0] : "";
					
				if(count($smatches)>0)
				{
					// build the kana-only and mixed-type strings
					$kanaonly=$pre;
					$mixed=$pre;
					
					foreach($smatches as $smatch) {
						$kanaonly .= preg_replace("/・/u", ", ",$smatch[2]) . (isset($smatch[3]) ? $smatch[3] : "");
						$mixed.= $smatch[1] . (isset($smatch[3]) ? $smatch[3] : ""); }

					// finally, de a fully qualified string replacement on the line
					$string = "$kanaonly ($mixed)";
				}
			}
			return $string;
		}
		
		// strip off nesting and mapping
		static function textclean($string)
		{
			// strip off mapping
			$pos = strpos($string, "@");
			if($pos!==false) { $string = substr($string,0,$pos); }

			// if there was no mapping, make sure to strip category nesting
			$pos = strpos($string, "!");
			if($pos!==false) { $string = substr($string,$pos+1); }
			
			// latex considered \" to mean "give the next character an umlaut",
			// so let's remove these slashes again
			$string = str_replace('\\"','"', $string);
			$string = str_replace("\\'","'", $string);
			
			return $string;
		}
		
		// FUCKIT: only capitalise if first character is an ascii character.
		// PHP is atrocious with multibyte strings.
		static function mb_ucfirst($string)
		{
			$f = mb_substr($string, 0, 1);
			$rest = mb_substr($string, 1);
			return (preg_match("/^\w$/u",$f)==1 ? strtoupper($f) : $f) . $rest;
		}
	}

?>