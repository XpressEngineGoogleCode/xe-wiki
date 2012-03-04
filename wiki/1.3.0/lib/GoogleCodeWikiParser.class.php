<?php

require_once('SyntaxParser.interface.php');
require_once('ParserBase.class.php');

class GoogleCodeWikiParser extends ParserBase {
	
	public function __construct($wiki_site){
		parent::__construct($wiki_site);
	}
}