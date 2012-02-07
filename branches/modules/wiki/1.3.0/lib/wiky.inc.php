<?php

class WikiSyntaxParser {
	private $patterns, $replacements;

	public function __construct($analyze=false) {
		$this->patterns=array(
			// Headings
			"/^==== (.+?) ====$/m",						// Subsubheading
			"/^=== (.+?) ===$/m",						// Subheading
			"/^== (.+?) ==$/m",						// Heading
	
			// Formatting
			"/\'\'\'\'\'(.+?)\'\'\'\'\'/s",					// Bold-italic
			"/\'\'\'(.+?)\'\'\'/s",						// Bold
			"/\'\'(.+?)\'\'/s",						// Italic
	
			// Special
			"/^----+(\s*)$/m",						// Horizontal line
			"/\[\[(file|img):((ht|f)tp(s?):\/\/(.+?))( (.+))*\]\]/i",	// (File|img):(http|https|ftp) aka image
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))( (.+))\]/i",		// Other urls with text
			"/\[((news|(ht|f)tp(s?)|irc):\/\/(.+?))\]/i",			// Other urls without text
	
			// Indentations
			"/[\n\r]: *.+([\n\r]:+.+)*/",					// Indentation first pass
			"/^:(?!:) *(.+)$/m",						// Indentation second pass
			"/([\n\r]:: *.+)+/",						// Subindentation first pass
			"/^:: *(.+)$/m",						// Subindentation second pass
	
			// Ordered list
			"/[\n\r]#.+([\n|\r]#.+)+/",					// First pass, finding all blocks
			"/[\n\r]#(?!#) *(.+)(([\n\r]#{2,}.+)+)/",			// List item with sub items of 2 or more
			"/[\n\r]#{2}(?!#) *(.+)(([\n\r]#{3,}.+)+)/",			// List item with sub items of 3 or more
			"/[\n\r]#{3}(?!#) *(.+)(([\n\r]#{4,}.+)+)/",			// List item with sub items of 4 or more
	
			// Unordered list
			"/[\n\r]\*.+([\n|\r]\*.+)+/",					// First pass, finding all blocks
			"/[\n\r]\*(?!\*) *(.+)(([\n\r]\*{2,}.+)+)/",			// List item with sub items of 2 or more
			"/[\n\r]\*{2}(?!\*) *(.+)(([\n\r]\*{3,}.+)+)/",			// List item with sub items of 3 or more
			"/[\n\r]\*{3}(?!\*) *(.+)(([\n\r]\*{4,}.+)+)/",			// List item with sub items of 4 or more
	
			// List items
			"/^[#\*]+ *(.+)$/m",						// Wraps all list items to <li/>
	
			// Newlines (TODO: make it smarter and so that it groupd paragraphs)
			"/^(?!<li|dd).+(?=(<a|strong|em|img)).+$/mi",			// Ones with breakable elements (TODO: Fix this crap, the li|dd comparison here is just stupid)
			"/^[^><\n\r]+$/m",						// Ones with no elements
		);
		$this->replacements=array(
			// Headings
			"<h3>$1</h3>",
			"<h2>$1</h2>",
			"<h1>$1</h1>",
	
			//Formatting
			"<strong><em>$1</em></strong>",
			"<strong>$1</strong>",
			"<em>$1</em>",
	
			// Special
			"<hr/>",
			"<img src=\"$2\" alt=\"$6\"/>",
			"<a href=\"$1\">$7</a>",
			"<a href=\"$1\">$1</a>",
	
			// Indentations
			"\n<dl>$0\n</dl>", // Newline is here to make the second pass easier
			"<dd>$1</dd>",
			"\n<dd><dl>$0\n</dl></dd>",
			"<dd>$1</dd>",
	
			// Ordered list
			"\n<ol>$0\n</ol>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
			"\n<li>$1\n<ol>$2\n</ol>\n</li>",
	
			// Unordered list
			"\n<ul>$0\n</ul>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
			"\n<li>$1\n<ul>$2\n</ul>\n</li>",
	
			// List items
			"<li>$1</li>",
	
			// Newlines
			"$0<br/>",
			"$0<br/>",
		);
		if($analyze) {
			foreach($this->patterns as $k=>$v) {
				$this->patterns[$k].="S";
			}
		}
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
			// {{{ This is some multiline code }}}
			$text = preg_replace("/
									{{{			# Starts with three braces
									([\n]*)		# Followed by one or more newlines
									(.+?)		# One or more characters (including line breaks, see s modifier)
									([\n]*)		# Followed by one or more newlines
									}}}			# And ends with another three braces
								 /sxe"    , "'<pre class=\'prettyprint\'>' . nl2br(htmlspecialchars(stripslashes('$2'))) . '</pre>'", $text);			
			// `This is a short snippet of code`
			$text = preg_replace("/`(.+?)`/"    , "<span class='inline_code'>$1</span>", $text);			
			
			// Replace bold			
			// *This be bold*
			$text = preg_replace("~
									(?<!		# Star is not preceded
										/)		#					by a / 
									[*]			# Starts with star
									([^ ])		# Must be followed by some non-space char
									(.+?)		# Any number of characters, including whitespace (see s modifier)
									[*]			# Ends with star
									(?!/)		# Star isn't followed by slash 
									~x", "<strong>$1$2</strong>", $text); // Bold is only replaced on the same line
			
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
			$text = preg_replace("/
									^		# Start from beginning of the line
									(		
									  (		
									    \n			# Newline
										\|\|		# ||
										.*			# Any text except |
										\|\|		# ||
									  )+			# One or more times
									)				# Capture all
									/msx", "<table border=1 cellspacing=0 cellpadding=5>\n$1\n</table>", $text);

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
			$text = preg_replace("/\n(.+)\n/", '<p>$1</p>', $text);
		}
		return $text;
	}
}