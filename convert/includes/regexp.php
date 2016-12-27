<?php
	//日本語UTF8 .... do I still even use this class?

	class RegExp
	{
		public static $kanji_chars = "\x{4E00}-\x{9FFFF}\x{3005}\x{30F6}";
		public static $kana_chars = "\x{3040}-\x{30FF}";
		public static $japanese_chars = "\x{4E00}-\x{9FFFF}\x{3005}\x{30F6}\x{3040}-\x{30FF}";
		
		// japanese when no non-japanese (fairly obvious)
		public static function is_japanese($string) { return preg_match("/[^".self::$japanese_chars."]/u", $string)==0; }

		// should be obvious, too
		public static function has_japanese($string) { return preg_match("/".self::$japanese_chars."/u", $string)>0; }
	}

?>