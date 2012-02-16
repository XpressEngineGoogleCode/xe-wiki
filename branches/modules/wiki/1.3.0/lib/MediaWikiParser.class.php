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
	
	
	
	/**
	 * @brief Searches for list blocks in a string
	 */
	protected function _getLists($text){
		$matches = array();
		$list_finder_regex = "/ (
						  (
						   [\r]?[\n]
						   [*#]+? #At least Star or #
						   [ ]
						   (.+)	# Any number of characters
						  )+
						)/x";		
		preg_match_all($list_finder_regex, $text, $matches, PREG_OFFSET_CAPTURE);
		return $matches[0];	
	}
	
}