<?php

require_once('ParserBase.class.php');

class MediaWikiParser extends ParserBase {	
	protected $typeface_symbols = array(
		"italic" => "\'\'",
		"bold"	=> "\'\'\'",
		"inline_code" => "`",
		"multiline_code_open" => "{{{",
		"multiline_code_close" => "}}}",
		"superscript" => '\^',
		"subscript" => ',,',
		"strikeout" => '~~'
	);
	
	public function __construct($wiki_site){
		parent::__construct($wiki_site);	
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
		// First pass, finding all blocks
		$this->text = preg_replace("/[\n\r]#.+([\n|\r]#.+)+/","\n<ol>$0\n</ol>", $this->text);
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
		
}