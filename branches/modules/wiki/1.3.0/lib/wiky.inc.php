<?php

class WikiSyntaxParser {
	// Number of blocks of code that will skip parsing;
	// By temporarily saving them in this array, we can later insert them back in inital string	
	private $code_blocks;
	
	// Number of code blocks injected back in initial text (after all other parsing is done)	
	private $replaced_code_blocks;
	
	// Number of code block batches replaced
	// Batch 1: all single line {{{ text }}}
	// Batch 2: all multiline {{{ text }}} etc.	
	private $batch_count;

	public function __construct() {
		$this->code_blocks = array();
		$this->replaced_code_blocks = 0;
		$this->batches = 0;
	}
	
	public function parse($text) {
		if(!empty($text)){
			// Convert Windows end of line (\r\n) to Linux (\n) end of line, otherwise preg_replace doesn't work
			$text = str_replace(chr(13), '', $text);

			// Replace headings
			// ====== Heading 6 ====== 
			$text = preg_replace("/^====== (.+?) ======( *)$/m"  , "<h6>$1</h6>", $text);
			// ===== Heading 5 ===== 
			$text = preg_replace("/^===== (.+?) =====( *)$/m"  , "<h5>$1</h5>", $text);
			// ==== Heading 4 ==== 
			$text = preg_replace("/^==== (.+?) ====( *)$/m"  , "<h4>$1</h4>", $text);
			// === Heading 3 === 
			$text = preg_replace("/
									^===\s     # Line starts with three equal signs, followed by a space
									(.+?)      # One or more characters (of any type except line breaks)
									\s===      # Followed by another space and three equal signs
									(\s*)$     # The line can end directly, or there can be spaces  
								  /mx", "<h3>$1</h3>", $text); // The m modifier specifies that matches are per line, instead of per document
			// == Heading 2 == 
			$text = preg_replace("/^== (.+?) ==( *)$/m"  , "<h2>$1</h2>", $text);
			// = Heading 1 =
			$text = preg_replace("/^= (.+?) =( *)$/m"    , "<h1>$1</h1>", $text);
			
			
			// Replace code blocks
			// We need to make sure text in code blocks is no longer parsed {{{ _italic_ }}} should skip the italic and leave text as is
			// For this, we use preg_replace_callback to save code blocks in an array that we will later inject back in the original string
			// {{{ This is some code }}}
			// $text = preg_replace("/[^`]{{{(.+?)}}}/me"    , "'<span class=\'inline_code\'>' . htmlentities('$1') . '</span>'", $text);	
			$this->batch_count++;
			$text = preg_replace_callback("/[^`]{{{(.+?)}}}/m"    , array($this, "parse_inline_code_block"), $text);	
			$this->batch_count++;
			$text = preg_replace_callback("/
									[^`]{{{			# Starts with three braces
									([\n]*)		# Followed by one or more newlines
									(.+?)		# One or more characters (including line breaks, see s modifier)
									([\n]*)		# Followed by one or more newlines
									}}}			# And ends with another three braces
								 /sx"    ,array($this, "parse_multiline_code_block") , $text);					
			// `This is a short snippet of code`
			$this->batch_count++;
			$text = preg_replace_callback("/`(.+?)`/m", array($this, "parse_inline_code_block"), $text);
			
			// Replace bold			
			// * This be bold*
			$text = preg_replace("~
									(?<!		# Star is not preceded
										/)		#					by a / 
									[*]			# Starts with star
									(.+?)		# Any number of characters, including whitespace (see s modifier)
									[*]			# Ends with star
									(?!/)		# Star isn't followed by slash 
									~x", " <strong>$1$2</strong> ", $text); // Bold is only replaced on the same line
			
			// Replace italic
			// _italics_ but not in_middle_of_word
			$text = preg_replace("/ _(.+?)_ /x", "<em>$1</em>", $text);
			// Replace ^super^script
			$text = preg_replace("/\^(.+?)\^/x", "<sup>$1</sup>", $text);
			// Replace ,,sub,,script
			$text = preg_replace("/,,(.+?),,/x", "<sub>$1</sub>", $text);
			// Replace ~~strikeout~~
			$text = preg_replace("/~~(.+?)~~/x", "<span style='text-decoration:line-through'>$1</span>", $text);
			
			
			// Replace #summary
			// #summary Summries are short descriptions of an article
			$text = preg_replace("/^#summary(.*)/m"    , "<i>$1</i>", $text);
			
			// Replace links
			// [https://my.link Click here]
			$text = preg_replace("/
									\[		# Starts with bracket
									([^ ]+)  # Text without spaces
									[ ]		# Space
									(.*)	# Any character
									\]		# Ends with bracket
									/x"    , "<a href='$1'>$2</a>", $text);

			// Unordered/Ordered list
            // # First pass, replace entire block (ul)
			$text = preg_replace("/
									(\s+)	# At least one space
									[#*]	# Star or #
									(.+)	# Any number of characters
									((\n)(\s+)[#*].+)*	# New line, and the above repeated any number of times
									/x", "<ul>\n$0\n</ul>", $text);		
            // # Second pass, replace li elements
			$text = preg_replace("/
									(\s+)	# At least one space
									([#*])	# A star or #
									\s+		# At least one space
									(.*)	# Any number of characters
									/x", "<li>$3</li>", $text);
                  
			// Tables
			// || 1 ||  2  ||  3 ||
			// First pass, replace <table>
			$text = preg_replace("/(
									(
									\|\|			# Start with ||
									.*				# Any character except newline
									\|\|			# Finish with ||
									[\r]?[\n]?		# Followed by an optional newline
									)+				# And repeat at least one time (table rows)
								)/x", "<table border=1 cellspacing=0 cellpadding=5>\n$1\n</table>", $text);

			// Second pass, replace rows (<tr>) and cells (<td>)
			$text = preg_replace("/
									^\|\|	# Line starts with ||
									/mx", "<tr><td>", $text);
			$text = preg_replace("/
									\|\|$	# Line ends with ||
									/mx", "</td></tr>", $text);
			$text = preg_replace("/	
									(\|\|)	# Any || found in text 
									/mx", "</td><td>", $text);
			            
			// Replace new lines with paragraphs
			$text = preg_replace("/\n(.+)/", '<p>$1</p>', $text);
			
			$text = $this->put_back_code_blocks($text);
		}
		return $text;
	}
	
	function parse_inline_code_block(&$matches){
		$this->code_blocks[] = '<span class=\'inline_code\'>' . htmlentities($matches[1]) . '</span>';
		return "%%%" . $this->batch_count . "%%%";
	}
	
	function parse_multiline_code_block(&$matches){
		$this->code_blocks[] = '<pre class=\'prettyprint\'>' . nl2br(htmlentities(stripslashes($matches[2]))) . '</pre>';
		return "%%%" . $this->batch_count . "%%%";
	}
	
	function put_back_code_blocks($text){
		for($i = 1; $i <= $this->batch_count; $i++){
			$text = preg_replace_callback(
					'/%%%'. $i .'%%%/',
					array($this, 'put_back_code_block'),
					$text
				);		
		}
		return $text;
	}
	
	function put_back_code_block(&$matches){
		return $this->code_blocks[$this->replaced_code_blocks++];
	}
}