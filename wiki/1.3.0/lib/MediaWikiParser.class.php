<?php

require_once('SyntaxParser.interface.php');
require_once('ParserBase.class.php');

class MediaWikiParser extends ParserBase {	
	protected $typeface_symbols = array(
		"italic" => "\'\'",
		"bold"	=> "\'\'\'",
		"inline_code" => "`",
		"multiline_code_open" => "[\n][ ][<]nowiki[>]",
		"multiline_code_close" => "[<][\/]nowiki[>]",
		"superscript" => '\^',
		"subscript" => ',,',
		"strikeout" => '~~'
	);
	
	public function __construct($wiki_site){
		parent::__construct($wiki_site);	
	}
		
	protected function parseText() {
		parent::parseText();

		$this->parseDefinitionLists();
		$this->parsePreformattedText();
	}
	
	/**
	 * Override
	 */
	protected function escapeWhateverThereIsToEscape(){
		// Escape text between <nowiki> and </nowiki>
		$this->batch_count++;
		$this->text = preg_replace_callback("~
												[<]nowiki[>]
												(.*)
												[<][/]nowiki[>]
											~x", array($this, "_escapeBlock"), $this->text);

		// Escape text right after <nowiki/>
		$this->batch_count++;
		$this->text = preg_replace_callback("~
												[<]nowiki[ ]?[/][>]
												([^ ]*)
											~x", array($this, "_escapeBlock"), $this->text);
	}	
	
	private function _escapeBlock(&$matches){
		$this->escaped_blocks[] = $matches[1];
		return "%%%" . $this->batch_count . "%%%";
	}
		
	protected function parseLists(){
		// Ordered list
		// Find all ordered list blocks - at most 4 levels deep - and surround them with <ol></ol>
		// First pass, finding all blocks
		$this->text = preg_replace("/						# This parses all list blocks (includes all LIs)
									[\n\r]					# Newline
									[#]						# That start with #
									.+						# Followed by any characters (at least one)
									([\n|\r][#].+)+			# Then repeat at least once
									/x","\n<ol>$0\n</ol>", $this->text);
		// List item with sub items of 2 or more
		$this->text = preg_replace("/[\n\r]#(?!#) *(.+)(([\n\r]#{2,}.+)+)/","\n<li>$1\n<ol>$2\n</ol>\n</li>", $this->text);
		// List item with sub items of 3 or more
		$this->text = preg_replace("/[\n\r]#{2}(?!#) *(.+)(([\n\r]#{3,}.+)+)/","\n<li>$1\n<ol>$2\n</ol>\n</li>", $this->text);	
		// List item with sub items of 4 or more
		$this->text = preg_replace("/[\n\r]#{3}(?!#) *(.+)(([\n\r]#{4,}.+)+)/","\n<li>$1\n<ol>$2\n</ol>\n</li>", $this->text);	
	
		// Unordered list
		// First pass, finding all blocks
		$this->text = preg_replace("/[\n\r]\*.+([\n|\r]\*.+)+/","\n<ul>$0\n</ul>", $this->text);	
		// List item with sub items of 2 or more
		$this->text = preg_replace("/[\n\r]\*(?!\*) *(.+)(([\n\r]\*{2,}.+)+)/","\n<li>$1\n<ul>$2\n</ul>\n</li>", $this->text);	
		// List item with sub items of 3 or more
		$this->text = preg_replace("/[\n\r]\*{2}(?!\*) *(.+)(([\n\r]\*{3,}.+)+)/","\n<li>$1\n<ul>$2\n</ul>\n</li>", $this->text);	
		// List item with sub items of 4 or more
		$this->text = preg_replace("/[\n\r]\*{3}(?!\*) *(.+)(([\n\r]\*{4,}.+)+)/","\n<li>$1\n<ul>$2\n</ul>\n</li>", $this->text);	
								
		// List items
		// Wraps all list items to <li/>		
		$this->text = preg_replace("/^[#\*]+ *(.+)$/m","<li>$1</li>", $this->text);	
	
		return;
	}
	
	/**
	 * Only allow one level lists - anything else is ignored
	 */
	protected function parseDefinitionLists(){
		// Wrap block with <dl> tags
		$this->text = preg_replace("/	
									[\r]?[\n]					# Newline
									[;:]						# That start with #
									.+						# Followed by any characters (at least one)
									(([\r]?[\n])[:;].+)+			# Then repeat at least once										
									/x","\n<dl>$0\n</dl>", $this->text);
		// Wrap term with <dt>
		$this->text = preg_replace("/^[;](.+)$/m","<dt>$1</dt>", $this->text);	
		
		// Wrap definitio with <dd>
		$this->text = preg_replace("/^[:](.+)$/m","<dd>$1</dd>", $this->text);	
	}
	
	protected function parseIndents(){
	}
	
	/**
	 * @override
	 * Skip blockquote parsing; indenting means Preformatted text in MediaWiki 
	 */
	protected function parseQuotes(){
		
	}
	
	protected function parsePreformattedText(){
		$this->text = preg_replace("/(
							(([\n])			# Start with newline
							[ ]				# One space
							(.+)			# Any number of characters
							)+				# Repeat at least once
							)/xe", "str_replace('$3', '', '<pre>$1</pre>')", $this->text);				
	}
	
	protected function parseLinks(){				
		// Replace external urls that just start with http, https, ftp etc.; skip the ones in square brackets
		$this->text = preg_replace("#
									(?<!
										[[]
									)
									((https?|ftp|file)
									://
									[^ ]*)
									#x","<a href=\"$1\" title=\"$1\" class=\"external\">$1</a>", $this->text);				
			
		
		// Find internal links given between [[double brackets]]
		//	- can contain piped description [my link|description that can have many words]
		//	- can link to local content [my link#local_anchor|and some description maybe]
		//	- can contain namespaced page names [Help:Contents|This is the Contents page]
		$this->text = preg_replace_callback("/
									[[][[]				# Starts with [[
									(([^#|]+?)[:])?		# Can start with something that ends in : [matches 1,2]
									([^#|]+?)?		# Followed by any word	[matches 3]
									([#](.*?))?		# Followed by an optional group that starts with # [matches 4,5]
									([|](.*?))?		# Followed by an optional group that starts with a pipe [matches 6,7]
									[]][]]				# Ends with ]]
									([^ ]*)?		# Optional tail for brackets - take all characters until first space  [matches 8]
								/x",array($this, "_handle_internal_link"), $this->text);		
		
		// Find external links given between [simple brackets]
		$this->text = preg_replace_callback("/
									[[]				# Starts with [
									([^#]+?)		# Followed by any word
									([#](.*?))?		# Followed by an optional group that starts with #
									([ ](.*?))?		# Followed by an optional group that starts with a space
									[]]				# Ends with ]
								/x",array($this, "_handle_external_link"), $this->text);			
	}
		
	
	private function _handle_external_link(&$matches) {
		$url = $matches[1];
		$local_anchor = $matches[2];
		$description = $matches[5];
		
		$href = '"' . $url . $local_anchor . '"';
		$title = " title=\"$url\"";
		$class = " class=\"external\"";
		
		$description = ($description ? $description : $url);
		
		return "<a href=$href$title$class>$description</a>";
	}
	
	/**
	 * Callback for call to preg_replace_callback that parses links
	 * array(9) {
	 *		[0]=> "[[Help:Main Page#See also|different text]]esses"
	 *		[1]=> "Help:"
	 *		[2]=> "Help"
	 *		[3]=> "Main Page"
	 *		[4]=> "#See also"
	 *		[5]=> "See also"
	 *		[6]=> "|different text"
	 *		[7]=> "different text"
	 *		[8]=> "esses"
	 *		}
	 * 
	 * @param array $matches
	 * @return string
	 */
	private function _handle_internal_link(&$matches){
		$namespace = $matches[2];
		$content = $matches[3]; // Page name or external url
		$local_anchor = $matches[4];
		$description = $matches[7];
		$tail = $matches[8];
		
		// If tail had a <nowiki /> tag before it, the words would have been removed from text by now
		// That is why we search for %%%		
		if(strpos($matches[8], '%%%') === 0) {
			$external_tail = $tail;
			$tail = '';
		}
		
		// Building href
		// url#local_anchor		
		$href = str_replace(' ', '_', $content) 
				. str_replace(' ', '_',$local_anchor);
		
		// Building title attribute
		$title = $content ? " title=\"$content\"" : '';
		
		// Add class attribute
		if(preg_match("/^(https?|ftp|file)/", $href)) $class = ' class="external"';
		
		// Build description
		if(!$description) $description = $content . $local_anchor;
		if($tail) $description .= $tail;
		
		
		return "<a href=\"$href\"$title$class>$description</a>$external_tail";
		
		$page_name = $matches[3];
		$url = str_replace(' ', '_', $page_name);
		$local_anchor = $matches[5];
		$description = $matches[7] ? $matches[7] : $page_name;
		
		
		$href = str_replace(' ', '_', $matches[3]) . $matches[4];
		
		

		if(strpos($matches[8], '%%%') === 0){
				$tail = '';
				$after_link = $matches[8];
		}
		else $tail = $matches[8];
		
		// If document exists, return expected link and exit
		if(preg_match("/^(https?|ftp|file)/", $url) || $alias = $this->wiki_site->documentExists($url)){
			return "<a href=\"$url$local_anchor\" title=\"$page_name\">" . $description . $tail . "</a>" . $after_link;
		}
		
		// Else, if document does not exist
		//   If user is not allowed to create content, return plain text
		if(!$this->wiki_site->currentUserCanCreateContent()) return $description . $tail;
		//   Else return link to create new page
		return "<a href=\"$url$local_anchor\" class=notexist>" . $description . $tail . "</a>" . $after_link;
	}	
		
}