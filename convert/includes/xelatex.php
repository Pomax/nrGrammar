<?php
	//日本語UTF8

	/**
	 * runs xelatex
	 */
	function xelatex($filename, $msg)
	{
		$texfile = $filename.".tex";
		$xelatex_command = "xelatex -halt-on-error ".$texfile;

		print("[".date("H:i:s")."] - ".$msg);
		$start = time();
		exec($xelatex_command, $result, $status);
		$end = time();
		println(" done (took ".($end-$start)."s).");
		if($status != 0) { 
			print("\n\nERRORS OCCURED WHILE RUNNING XELATEX (STATUS RETURN:$status) - LISTING OUTPUT\n\n");
			foreach($result as $line) { fwrite(STDERR, $line."\n"); }
			exit(-1); }

	}

	/**
	 * makes the glossary file
	 */
	function makeglossary($filename)
	{
		global $stepthrough;

		print("[".date("H:i:s")."] - Running makeindex to generated the glossary...");
		$start = time();
		exec("makeindex -s $filename.ist -t $filename.glg -o $filename.gls $filename.glo", $result, $status);
		$end = time();
		println(" done (took ".($end-$start)."s).");
		if($status != 0) { 
			print("\n\nERRORS OCCURED WHILE RUNNING MAKEINDEX FOR THE GLOSSARY (STATUS RETURN:$status) - SHOWING OUTPUT\n\n");
			foreach($result as $line) { fwrite(STDERR, $line."\n"); }
			exit(-1); }
	}
	
	/**
	 * makes the index file, and corrects it
	 */
	function makeindex($filename)
	{
		print("[".date("H:i:s")."] - Running makeindex, to generate the regular index...");
		$start = time();
		exec("makeindex $filename.idx", $result, $status);
		$end = time();
		println(" done (took ".($end-$start)."s).");
		
		if($status != 0) { 
			print("\n\nERRORS OCCURED WHILE RUNNING MAKEINDEX FOR THE INDEX (STATUS RETURN:$status) - SHOWING OUTPUT\n\n");
			foreach($result as $line) { fwrite(STDERR, $line."\n"); }
			exit(-1); }


		// makeindex does not put gaps in non-latin index lists, so all the Japanese is one huge blob.
		// This is corrected here.
		print("[".date("H:i:s")."] - Correcting the generated index...");
		$start = time();

		$indexdata = file($filename.".ind");
		$kana = "[\x{3040}-\x{30FF}]+";
		$old = "";

		$fh = fopen($filename.".ind","w");
		// step one: bind English index in ToC
		fwrite($fh, "\\bigsection\n");
		fwrite($fh, "\\setcounter{secnumdepth}{0}\n");
		$jp=false;

		// step two: make sure there are gaps between Japanese sections
		fwrite($fh, "\\addcontentsline{toc}{chapter}{Indexes}\n");
		for($l=0; $l<count($indexdata); $l++) {
			$line = $indexdata[$l];
			if($l==0) {
				$line = $line . "\\setindextitle{English index}\n";
				$line = $line . "\\markboth{English index}{}\n"; }
			if(strpos($line, "\item ")!==false && preg_match("/$kana/u",$line)) {
				$cmp = substr($line, 8);
				$fc = mb_substr($cmp,0,1,'UTF-8');
				if(trim($fc)!="" && $old != $fc) {
					$old=$fc;
					if(preg_match("/$kana/u",$fc)>0 && $jp==false) {
						// step three: bind Japanese index in ToC
						$jp=true;
						fwrite($fh, "\\end{theindex}\n");
						fwrite($fh, "\\cleardoublepage\n");
						fwrite($fh, "\\setindextitle{Japanese index}\n");
						fwrite($fh, "\\markboth{Japanese index}{}\n");
						fwrite($fh, "\\begin{theindex}\n"); }
					fwrite($fh, "  \\indexspace\n\n"); }}
			fwrite($fh, $line); }
		fclose($fh);
		$end = time();
		println(" done (took ".($end-$start)."s).");
	}

	/**
	 * removes all the files necessary during the run, but irrelevant once the pdf file's built
	 */
	function unlinkfiles($filename)
	{
		// xelatex related
		unlink($filename.".aux");
		unlink($filename.".out");
		unlink($filename.".toc");
		unlink($filename.".log");
		
		// glossaries related
		unlink($filename.".ist");
		unlink($filename.".glg");
		unlink($filename.".glo");
		unlink($filename.".gls");

		// makeidx related
		unlink($filename.".idx");
		unlink($filename.".ilg");
		unlink($filename.".ind");
	}

	/**
	 * Runs all the latex commands in sequence
	 */
	function run_xelatex($filename)
	{
		global $unlink, $stepthrough;
		println("");
		$op=1;

		// run through the motions
		xelatex($filename, "Running XeLaTeX for the first time (sit back, this will take a while)...");
		if($stepthrough) copy($filename.".pdf", $filename.".".($op++).".pdf");
		xelatex($filename, "Running XeLaTeX to incorporate the ToC...");
		if($stepthrough) copy($filename.".pdf", $filename.".".($op++).".pdf");
		makeglossary($filename);
		xelatex($filename, "Running XeLaTeX to incorporate the glossary...");
		if($stepthrough) copy($filename.".pdf", $filename.".".($op++).".pdf");
		makeindex($filename);
		xelatex($filename, "Running XeLaTeX to incorporate the index...");
		if($stepthrough) copy($filename.".pdf", $filename.".".($op++).".pdf");
		xelatex($filename, "Running final XeLaTeX realignment run...");
		if($stepthrough) copy($filename.".pdf", $filename.".".($op++).".pdf");
		
		// if we've not exited by now, leave only the .tex, .log and .pdf file
		if($unlink) { unlinkfiles($filename); }
	}

?>