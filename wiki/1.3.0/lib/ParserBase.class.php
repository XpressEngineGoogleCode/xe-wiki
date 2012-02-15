<?php

class ParserBase {
	// Number of blocks of text that will skip parsing;
	// By temporarily saving them in this array, we can later insert them back in inital string	
	protected $escaped_blocks;
	
	// Number of text blocks injected back in initial text (after all other parsing is done)	
	protected $replaced_escaped_blocks;
	
	// Number of code block batches replaced
	// Batch 1: all single line {{{ text }}}
	// Batch 2: all multiline {{{ text }}} etc.	
	protected $batch_count;

	// Text being parsed
	protected $text;
	
	protected $typeface_symbols = array(
		"italic" => "_",
		"bold"	=> "\*",
		"inline_code" => "`",
		"multiline_code_open" => "{{{",
		"multiline_code_close" => "}}}",
		"superscript" => '\^',
		"subscript" => ',,',
		"strikeout" => '~~'
	);
	
	public function __construct() {
		$this->escaped_blocks = array();
		$this->replaced_escaped_blocks = 0;
		$this->batch_count = 0;
		$this->text = '';
	}
	
	public function parse($text) {
		$this->text = $text;
		if(!empty($this->text)){
			// Convert Windows end of line (\r\n) to Linux (\n) end of line, otherwise preg_replace doesn't work
			$this->text = str_replace(chr(13), '', $this->text);			
			
			$this->escapeWhateverThereIsToEscape();
			$this->parseCodeBlocksAndEscapeThemFromParsing();
			
			
			$this->parseHeadings();
			$this->parseBoldUnderlineAndSuch();
			$this->parsePragmas();
			$this->parseLinks();
			$this->parseLists();
			$this->parseTables();
			$this->parseQuotes();
			$this->parseHorizontalRules();		
			$this->parseParagraphs();
		
			$this->putBackEscapedBlocks();
		}
		return $this->text;
	}
	
	protected function escapeWhateverThereIsToEscape(){
		
	}
	
	/**
	 * @brief Parses a block of code
	 */
	protected function parseCodeBlocksAndEscapeThemFromParsing(){
		// Replace code blocks
		// We need to make sure text in code blocks is no longer parsed {{{ _italic_ }}} should skip the italic and leave text as is
		// For this, we use preg_replace_callback to save code blocks in an array that we will later inject back in the original string
		// {{{ This is some code }}}
		// $text = preg_replace("/[^`]{{{(.+?)}}}/me"    , "'<span class=\'inline_code\'>' . htmlentities('$1') . '</span>'", $text);	
		$this->batch_count++;
		$regex_find_singleline_multiline_code = "/(?<![`])" . $this->typeface_symbols["multiline_code_open"] ."(.+?)" . $this->typeface_symbols["multiline_code_close"] ."/m";
		$this->text = preg_replace_callback($regex_find_singleline_multiline_code    , array($this, "_parseInlineCodeBlock"), $this->text);	
		$this->batch_count++;
		$this->text = preg_replace_callback("/
								(?<![" . $this->typeface_symbols["inline_code"] . "])" . $this->typeface_symbols["multiline_code_open"] . "	# Starts with three braces, not preceded by single line code symbol
								([\n]*)		# Followed by one or more newlines
								(.+?)		# One or more characters (including line breaks, see s modifier)
								([\n]*)		# Followed by one or more newlines
								" . $this->typeface_symbols["multiline_code_close"] . "			# And ends with another three braces
								/sx"    ,array($this, "_parseMultilineCodeBlock") , $this->text);					
		// `This is a short snippet of code`
		$this->batch_count++;
		$regex_find_inline_code = "/" . $this->typeface_symbols["inline_code"] . "(.+?)" . $this->typeface_symbols["inline_code"] . "/m";
		$this->text = preg_replace_callback($regex_find_inline_code, array($this, "_parseInlineCodeBlock"), $this->text); 
	}
	
	/**
	 * @brief preg_replace callback function for inline code blocks
	 *  - replaces wiki syntax code block with HTML span and saves it locally
	 *  - removes wiky syntax code block from initial string and replaces it with dummy text
	 *  - reference will be later injected back in string, after all other parsing is done
	 * The purpose of this function is to skip parsing text inside code blocks
	 */
	private function _parseInlineCodeBlock(&$matches){
		$this->escaped_blocks[] = '<tt>' . htmlentities($matches[1]) . '</tt>';
		return "%%%" . $this->batch_count . "%%%";
	}

	/**
	 * @brief preg_replace callback function for multiline code blocks
	 *  - replaces wiki syntax code block with HTML pre and saves it locally
	 *  - removes wiky syntax code block from initial string and replaces it with dummy text
	 *  - reference will be later injected back in string, after all other parsing is done
	 * The purpose of this function is to skip parsing text inside code blocks
	 */	
	private function _parseMultilineCodeBlock(&$matches){
		$this->escaped_blocks[] = '<pre class=\'prettyprint\'>' . nl2br(htmlentities(stripslashes($matches[2]))) . '</pre>';
		return "%%%" . $this->batch_count . "%%%";
	}
	
	/**
	 * @brief Injects code blocks back into initial string
	 */
	protected function putBackEscapedBlocks(){
		for($i = 1; $i <= $this->batch_count; $i++){
			$this->text = preg_replace_callback(
					'/%%%'. $i .'%%%/',
					array($this, '_putBackEscapedBlock'),
					$this->text
				);		
		}
	}
	
	/**
	 * @brief preg_replace callback function for injecting code blocks back in initial string
	 */
	private function _putBackEscapedBlock(&$matches){
		return $this->escaped_blocks[$this->replaced_escaped_blocks++];
	}
	
	/**
	 * @brief Parses headings 
	 * TODO: Also add anchors (for local links)
	 */
	protected function parseHeadings(){
		// Replace headings
		// ====== Heading 6 ====== 
		$this->text = preg_replace("/^====== (.+?) ======( *)$/m"  , "<h6>$1</h6>", $this->text);
		// ===== Heading 5 ===== 
		$this->text = preg_replace("/^===== (.+?) =====( *)$/m"  , "<h5>$1</h5>", $this->text);
		// ==== Heading 4 ==== 
		$this->text = preg_replace("/^==== (.+?) ====( *)$/m"  , "<h4>$1</h4>", $this->text);
		// === Heading 3 === 
		$this->text = preg_replace("/
								^===\s     # Line starts with three equal signs, followed by a space
								(.+?)      # One or more characters (of any type except line breaks)
								\s===      # Followed by another space and three equal signs
								(\s*)$     # The line can end directly, or there can be spaces  
								/mx", "<h3>$1</h3>", $this->text); // The m modifier specifies that matches are per line, instead of per document
		// == Heading 2 == 
		$this->text = preg_replace("/^== (.+?) ==( *)$/m"  , "<h2>$1</h2>", $this->text);
		// = Heading 1 =
		$this->text = preg_replace("/^= (.+?) =( *)$/m"    , "<h1>$1</h1>", $this->text);
	}
	
	/**
	 * @brief Parse bold, italic, underline etc.
	 */
	protected function parseBoldUnderlineAndSuch(){
		// Replace bold			
		// * This be bold*
		$this->text = preg_replace("~
								(?<!		# Star is not preceded
									/)		#					by a / 
								". $this->typeface_symbols["bold"] . "			# Starts with star
								(.+?)		# Any number of characters, including whitespace (see s modifier)
								". $this->typeface_symbols["bold"] . "			# Ends with star
								(?!/)		# Star isn't followed by slash 
								~x", "<strong>$1$2</strong>", $this->text); // Bold is only replaced on the same line

		// Replace italic
		// _italics_ but not in_middle_of_word
		$this->text = preg_replace("/(?<![^ \n*>])". $this->typeface_symbols["italic"] . "(.+?)". $this->typeface_symbols["italic"] . "/x", "<em>$1</em>", $this->text);
		// Replace ^super^script
		$this->text = preg_replace("/". $this->typeface_symbols["superscript"] . "(.+?)". $this->typeface_symbols["superscript"] . "/x", "<sup>$1</sup>", $this->text);
		// Replace ,,sub,,script
		$this->text = preg_replace("/". $this->typeface_symbols["subscript"] . "(.+?)". $this->typeface_symbols["subscript"] . "/x", "<sub>$1</sub>", $this->text);
		// Replace ~~strikeout~~
		$this->text = preg_replace("/". $this->typeface_symbols["strikeout"] . "(.+?)". $this->typeface_symbols["strikeout"] . "/x", "<span style='text-decoration:line-through'>$1</span>", $this->text);		
	}
	
	/**
	 * Optional pragma lines provide metadata about the page and how it should be displayed. 
	 * These lines are only processed if they appear at the top of the file. 
	 * Each pragma line begins with a pound-sign (#) and the pragma name, followed by a value. 
	 */
	protected function parsePragmas(){
		// Replace #summary
		// #summary Summries are short descriptions of an article
		$this->text = preg_replace("/^#summary[ ]?(.*)/m"    , "<i>$1</i>", $this->text);		
	}

	/**
	 * Links
	 * 	Internal links
	 * 		- to pages that do not exist -> show up with ? after, and link to page creation form, if logged in; otherwise, leave as plain text
	 * 		- to pages that exist
	 * 		WikiWord, [Nonwikiword], [PageTitle Description], !WikiWordEscaped
	 * 		- to local anchors -> defined by h1, h2 etc TODO
	 *  Links to issues and revisions
	 *	Links to extenal pages
	 *		- anyhting that starts with http, https, ftp 
	 *		- [URL description]
	 * 		- anything that starts with http, https, ftp and ends with png, gif, jpg, jpeg -> image
	 * 		- [Url ImageUrl] -> image links	
	 */
	protected function parseLinks(){
		// Replace links
		// [https://my.link Click here]
//		$this->text = preg_replace("/
//								\[		# Starts with bracket
//								([^ ]+)  # Text without spaces
//								[ ]		# Space
//								(.*)	# Any character
//								\]		# Ends with bracket
//								/x"    , "<a href='$1'>$2</a>", $this->text);		
		
		// Find internal links given as CamelCase words
		$this->text = preg_replace("/
								(
								(?<!			# Doesn't begin with ..
									( 
									[!]			#   .. a ! (these need to be escaped)
									|			#   or
									[[]			#   .. a [ (these will be treated later)
									)
								)	
								(				# Sequence of letters that ..
									[A-Z]	    # Start with an uppercase letter
									[a-z0-9]+	# Followed by at least one lowercase letter
								){2,}			# Repeated at least two times
								)	
								/x", "<a href=$1>$1</a>", $this->text);
		// Remove exclamation marks from CamelCase words
		$this->text = preg_replace("/(!)(([A-Z][a-z0-9]+){2,})/x", '$2', $this->text);
		
		// Replace image URLs with img tags
		$this->text = preg_replace("#
									(https?|ftp|file)
									://
									[^ ]*?
									(.gif|.png|.jpe?g)
									#x", "<img src=$0 />", $this->text);		
		
		// Replace external urls that just start with http, https, ftp etc.; skip the ones in square brackets

		$this->text = preg_replace("#
									(?<!
										(
										[[]
										|
										[=]
										)
									)
									((https?|ftp|file)
									://
									[^ ]*)
									#x", "<a href=$2>$2</a>", $this->text);		
		
		// Find internal links given between [brackets]
		//	- can contain description [myLink description that can have many words]
		//	- can link to local content [myLink#local_anchor and some description maybe]
		// Also catches external links
		// TODO treat images separately
		$this->text = preg_replace("/
									[[]				# Starts with [
									([^#]+?)		# Followed by any word
									([#](.*?))?		# Followed by an optional group that starts with #
									([ ](.*?))?		# Followed by an optional group that starts with a space
									[]]				# Ends with ]
								/xe", "'<a href=$1$2>' . ('$5' ? '$5' : '$1') . '</a>'", $this->text);
		



		
		// [-A-Z0-9+&@\#/%?=~_|!:,.;]*[A-Z0-9+&@\#/%=~_|]
		
		
		
		
		
		
		// Internal links
		// Between [ and ] or automatic
		// Can be
		//		- simple: ThisIsAWikiWord so it will be linked
		//		- between brackets [Mypage] or [Mypage | Description time]
		//			also [Main page] and [Main p age | wassup] -> replace space with underscore
		//		- with local anchor [MyPage#Introduction] or [MyPage#How_you-doing | How you doin?]
		
	}
	
	
	/**
	 * @brief Replaces Wiki Syntax lists with HTML lists
	 */
	protected function parseLists(){
		$this->text = $this->_parseLists($this->text);
	}
	
	private function _parseLists($text){
		$lists = $this->_getLists($text);
		$offset_error = 0; // Length of final string changes during the function, so offset needs to be adjusted
		
		foreach($lists as $list_info){
			$list = $list_info[0];
			$list_offset = $list_info[1];
			
			$new_list = $this->_parseList($list);
			$new_list = $this->_parseLists($new_list);
			$offset_error -= strlen($text);
			$text = substr_replace($text, $new_list, $list_offset + $offset_error, strlen($list));
			$offset_error += strlen($text);
		}	
		return $text;				
	}
	
	/**
	 * @brief Replaces a block of text containing a Wiki syntax list with an HTML list 
	 * Parses only first level list (in case we have nested lists)
	 */
	private function _parseList($list){
		$list = str_replace(' ', '@', $list);
		$i = 0;
		$char = substr($list, $i, 1);
		while(!in_array($char, array('*', '#'))) { 
			$i++; 
			$char = substr($list, $i, 1);
		}
		if($char == '*') $list_type = 'ul';
		else $list_type = 'ol';
		
		$current_list_indent = substr($list, 0, $i);
		
		// Remove indenting for current indentation level
		$regex = '/^'. trim($current_list_indent) .'(.*)/m';
		$list = preg_replace($regex, '$1', $list);
		
		// Replace list items
		$regex = '/^[' . $char . ']@?(.*)/m';
		$list = preg_replace($regex, '<li>$1</li>', $list);
		$list = str_replace('@', ' ', $list);

		// Add block tags
		$list = '<' . $list_type . '>'. $list . '</' . $list_type . '>';
		
		return $list;
	}
	
	/**
	 * @brief Searches for list blocks in a string
	 */
	private function _getLists($text){
		$matches = array();
		$list_finder_regex = "/ (
						  (
						   [\r]?[\n]
						   [ ]+	# At least one space
						   [*#]	# Star or #
						   (.+)	# Any number of characters
						  )+
						)/x";		
		preg_match_all($list_finder_regex, $text, $matches, PREG_OFFSET_CAPTURE);
		return $matches[0];	
	}	

	/**
	 * Tables
	 * || 1 ||  2  ||  3 ||
	 */
	public function parseTables(){
		// Tables
		// || 1 ||  2  ||  3 ||
		// First pass, replace <table>
		$this->text = preg_replace("/(
								(
								\|\|			# Start with ||
								.*				# Any character except newline
								\|\|			# Finish with ||
								[\r]?[\n]?		# Followed by an optional newline
								)+				# And repeat at least one time (table rows)
							)/x", "<table border=1 cellspacing=0 cellpadding=5>\n$1\n</table>", $this->text);

		// Second pass, replace rows (<tr>) and cells (<td>)
		$this->text = preg_replace("/
								^\|\|	# Line starts with ||
								/mx", "<tr><td>", $this->text);
		$this->text = preg_replace("/
								\|\|$	# Line ends with ||
								/mx", "</td></tr>", $this->text);
		$this->text = preg_replace("/	
								(\|\|)	# Any || found in text 
								/mx", "</td><td>", $this->text);
		
	}
	
	/**
	 * 
	 */
	public function parseQuotes(){
		// Replace quotes
		//	  Inferred by indentation
		$this->text = preg_replace("/(
							(([\n])
							[ ]	
							(.+)	
							)+
							)/xe", "str_replace('$3', '', '<blockquote>$1</blockquote>')", $this->text);		
	}
	
	public function parseHorizontalRules(){
		// Replace horizontal rule
		// ----
		$this->text = preg_replace("/^-{4,}/m", "<hr />", $this->text);		
	}
	
	public function parseParagraphs(){
		// Replace new lines with paragraphs
		$this->text = preg_replace("/\n(.+)/", '<p>$1</p>', $this->text);		
	}
	
}