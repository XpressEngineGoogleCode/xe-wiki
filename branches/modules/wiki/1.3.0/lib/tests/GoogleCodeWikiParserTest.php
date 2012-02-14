<?php

require_once('../GoogleCodeWikiParser.class.php');

class GoogleCodeWikiParserTest extends PHPUnit_Framework_TestCase
{
	protected $wikiParser = null;
	
	protected function setUp(){
		$this->wikiParser = new GoogleCodeWikiParser();
	}
	
	/**
	 * #summary	 One-line summary of the page 
	 */
	public function testPragmasSummary()
	{
		// When found at the beginning of the line, convert to italic
		$output = $this->wikiParser->parse('#summary How you doing?');
		$this->assertEquals('<i>How you doing?</i>', $output);
		
		// When found inside document text, parsing should skip it
		// Also, it should not be converted to a list either 
		$output = $this->wikiParser->parse('Some text before should make it invalid #summary How you doing?');
		$this->assertEquals('Some text before should make it invalid #summary How you doing?', $output);		
	}
	
	/**
	 * #labels	 Comma-separated list of labels (filled in automatically via the web UI) 
	 */
	public function testPragmasLabels(){
		$this->markTestSkipped("Label support was not implemented");
	}
	
	/**
	 * #sidebar	 See Side navigation http://code.google.com/p/support/wiki/WikiSyntax#Side_navigation
	 */
	public function testPragmasSidebar(){
		$this->markTestSkipped("Sidebar support was not implemented");
	}
	
	/**
	 * Paragraphs - Use one or more blank lines to separate paragraphs.
	 */
	public function testParagraphs(){
		$output = $this->wikiParser->parse("\nA paragraph");
		$this->assertEquals("<p>A paragraph</p>", $output);
	} 
	
	/**
	 * italic	_italic_ 
	 */
	public function testTypefaceItalic(){
		$output = $this->wikiParser->parse("_italic_");
		$this->assertEquals("<em>italic</em>", $output);
	}
	
	/**
	 * bold	*bold* 
	 */
	public function testTypefaceBold(){
		$output = $this->wikiParser->parse("*bold*");
		$this->assertEquals("<strong>bold</strong>", $output);
	}
	
	/**
	 * code	`code`
	 */
	public function testTypefaceCodeInline(){
		$output = $this->wikiParser->parse("`code`");
		$this->assertEquals("<span class='inline_code'>code</span>", $output);
	}	
	

	/**
	 * code	{{{{code}}}
	 */
	public function testTypefaceCodeMultiline(){
		$output = $this->wikiParser->parse("{{{code}}}");
		$this->assertEquals("<pre class='prettyprint'>code</pre>", $output);
	}		

}
