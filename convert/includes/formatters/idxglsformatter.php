<?php
//日本語UTF8

require_once('formatter.php');

// Declare these as global so they're accessible even when this file is included from within a function
global $codepoints, $japanese, $first_japanese, $error_log;

$codepoints = "[\x{3000}-\x{3100}\x{3400}-\x{4DC0}\x{4E00}-\x{A000}\x{FF00}-\x{FFF0}]";
$japanese = "/$codepoints+/u";
$first_japanese = "/^$codepoints+/u";

$error_log = "index-errors.log";
fclose(fopen($error_log, "w"));


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
		$gloss_pattern = "/{gls:([^:]+):([^}]+)}/u";
		$idx_pattern   = "/{idx:([^:]+):([^}]+)}/u";

		for ($line = 0; $line < count($text); $line++) {
			$content = $text[$line];
			$line_empty = trim($content) == "";

			// Rewrite glossary entries from wiki syntax to LaTeX syntax

			$text[$line] = preg_replace_callback($gloss_pattern, function ($matches) {
				$term = $matches[2];
				return '\\glslink{' . strtolower(IdxGlsFormatter::idx_clean($term)) . '}{' . IdxGlsFormatter::text_clean($term) . '}';
			}, $text[$line]);

			if (!$line_empty && trim($text[$line]) == "") {
				print("\n\n--- ERROR! GOBBLED LINE $line AT GLOSS PARSE: $content\n");
				// revert
				$text[$line] = $content;
				exit(0);
			}

			// Rewrite index entries from wiki syntax to LaTeX syntax

			$text[$line] = preg_replace_callback($idx_pattern, function ($matches) {
				global $japanese, $error_log;
				$language = $matches[1];
				$term = $matches[2];
				// double check the language
				if ($language == "english" && preg_match($japanese, $term) > 0) {
					file_put_contents($error_log, $matches[0] . "\n", FILE_APPEND | LOCK_EX);
					$language = "japanese";
				}
				return '\\index[' . $language . ']{' . IdxGlsFormatter::idx_clean($term) . '}' . IdxGlsFormatter::text_clean($term);
			}, $text[$line]);

			if (!$line_empty && trim($text[$line]) == "") {
				print("\n\n--- ERROR! GOBBLED LINE $line AT IDX PARSE: $content\n");
				// revert
				$text[$line] = $content;
			} else {
				$content = $text[$line];
			}
		}
	}

	// helper function for filtering index entries
	static function idx_clean($string)
	{
		global $japanese;
		global $first_japanese;

		$og = $string;
		$hit = $og == "迷惑(めいわく)の受身(うけみ)";
		if ($hit) println("\n\n$string\n\n");

		//			echo " (idx in: $string) ";

		$is_mapped = (preg_match("/@/u", $string) > 0);
		$is_nested = (preg_match("/!/u", $string) > 0);

		// mapped intuitively (A@B = A indexed as B)?

		if ($is_mapped) {
			if ($hit) println("\n\n$string is mapped\n\n");

			$terms = preg_split("/@/u", $string);
			$string = $terms[1];
		}

		// nested?

		if ($is_nested) {
			if ($hit) println("\n\n$string is nested\n\n");

			$cats = preg_split("/!/u", $string);
			for ($c = 0; $c < count($cats); $c++) {
				$cat = &$cats[$c];
				// echo " (split cat $c: $cat) ";
				if (preg_match($japanese, $cat) == 0) {
					$cat = str_replace('\\"', '', $cat);
					$cat = str_replace("\\'", '', $cat);
					$cat = ($c == 0) ? IdxGlsFormatter::mb_ucfirst($cat) : mb_convert_case($cat, MB_CASE_LOWER, "UTF-8");
					// echo " (cat after rewrite: $cat) ";
				} else {
					$cat = IdxGlsFormatter::unrubify($cat);
					// echo " (cat after unruby: $cat) ";
				}
			}
			// respect the English language at least a little
			$last = &$cats[count($cats) - 1];
			if ($last == "i") {
				$last = "I";
			}
			// respect language names
			$last = str_replace("japan", "Japan", $last);
			$last = str_replace("chin", "Chin", $last);
			$last = str_replace("engl", "Engl", $last);
			$string = implode("!", $cats);
		}

		// not mapped or nested, so just capitalise the first word (if it isn't japanese of course).
		if (preg_match($first_japanese, $string) == 0) {
			if ($hit) println("\n\n$string is not Japanese\n\n");

			$string = str_replace('\\"', '', $string);
			$string = str_replace("\\'", '', $string);
			$string = IdxGlsFormatter::mb_ucfirst($string);
			// echo " (idx !m!n, if: $string) ";
		}

		// if it *is* Japanese, unrubify it.
		else {
			if ($hit) println("\n\n$string needs unruby\n\n");

			$string = IdxGlsFormatter::unrubify($string);
			// echo " (idx !m!n, !if: $string) ";
		}


		if ($hit) println("\n\n$string done\n\n");

		return $string;
	}

	// converts a string a la \ruby{base}{furi} into "furi (base)"
	static function unrubify($string)
	{
		// if there is only japanese (=no western text), unravel
		if (preg_match("/[a-zA-Z]/u", $string) == 0) {
			$kanji = "\x{4E00}-\x{9FFF}\x{3005}\x{30F6}";
			$kana = "\x{3040}-\x{30FF}";
			$block = "/([" . $kanji . ']+)\(([' . $kana . ']+)\)([' . $kana . ']*)/u';
			preg_match_all($block, $string, $s_matches, PREG_SET_ORDER);

			$pre = preg_split($block, $string);
			$pre = (count($pre) > 0) ? $pre[0] : "";

			if (count($s_matches) > 0) {
				// build the kana-only and mixed-type strings
				$kana_only = $pre;
				$mixed = $pre;

				foreach ($s_matches as $s_match) {
					$kana_only .= preg_replace("/・/u", ", ", $s_match[2]) . (isset($s_match[3]) ? $s_match[3] : "");
					$mixed .= $s_match[1] . (isset($s_match[3]) ? $s_match[3] : "");
				}

				// finally, de a fully qualified string replacement on the line
				$string = "$kana_only ($mixed)";
			}
		}
		return $string;
	}

	// strip off nesting and mapping
	static function text_clean($string)
	{
		// strip off mapping
		$pos = strpos($string, "@");
		if ($pos !== false) {
			$string = substr($string, 0, $pos);
		}

		// if there was no mapping, make sure to strip category nesting
		$pos = strpos($string, "!");
		if ($pos !== false) {
			$string = substr($string, $pos + 1);
		}

		// latex considered \" to mean "give the next character an umlaut",
		// so let's remove these slashes again
		$string = str_replace('\\"', '"', $string);
		$string = str_replace("\\'", "'", $string);

		return $string;
	}

	// Only capitalise if first character is an ascii character.
	static function mb_ucfirst($string)
	{
		// PHP is atrocious with multibyte strings.
		$f = mb_substr($string, 0, 1);
		$rest = mb_substr($string, 1);
		return (preg_match("/^\w$/u", $f) == 1 ? strtoupper($f) : $f) . $rest;
	}
}
