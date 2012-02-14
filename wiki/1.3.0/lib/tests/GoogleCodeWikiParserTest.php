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
		
		$input_string = <<<INPUT
{{{
def fib(n):
  if n == 0 or n == 1:
    return n
  else:
    # This recursion is not good for large numbers.
    return fib(n-1) + fib(n-2)
}}}
INPUT;
		$output = $this->wikiParser->parse($input_string);
		$expected_string = <<<EXPECTED
<pre class='prettyprint'>def fib(n):<br />
  if n == 0 or n == 1:<br />
    return n<br />
  else:<br />
    # This recursion is not good for large numbers.<br />
    return fib(n-1) + fib(n-2)</pre>
EXPECTED;
		$expected_string = str_replace(chr(13), '', $expected_string);
		
		$this->assertEquals($expected_string,$output);
	}		

	/**
	 * superscript	^super^script
	 */
	public function testTypefaceSuperscript(){
		$output = $this->wikiParser->parse("^super^script");
		$this->assertEquals("<sup>super</sup>script", $output);
	}			
	
	/**
	 * subscript	,,sub,,script
	 */
	public function testTypefaceSubscript(){
		$output = $this->wikiParser->parse(",,sub,,script");
		$this->assertEquals("<sub>sub</sub>script", $output);
	}				
	
	/**
	 * strikeout ~~strikeout~~
	 */
	public function testTypefaceStrikeout(){
		$output = $this->wikiParser->parse("~~strikeout~~");
		$this->assertEquals("<span style='text-decoration:line-through'>strikeout</span>", $output);
	}					
	
	/**
	 * Mixed typeface styles 
	 */
	public function testTypefaceCombinations(){
		$output = $this->wikiParser->parse("_*bold* in italics_");
		$this->assertEquals("<em><strong>bold</strong> in italics</em>", $output);
		
		$output = $this->wikiParser->parse("*_italics_ in bold*");
		$this->assertEquals("<strong><em>italics</em> in bold</strong>", $output);

		$output = $this->wikiParser->parse("*~~strike~~ works too*");
		$this->assertEquals("<strong><span style='text-decoration:line-through'>strike</span> works too</strong>", $output);
		
		$output = $this->wikiParser->parse("~~as well as _this_ way round~~");
		$this->assertEquals("<span style='text-decoration:line-through'>as well as <em>this</em> way round</span>", $output);		
	}
}
