<?php

require_once('../MediaWikiParser.class.php');

class MediaWikiParserTest extends PHPUnit_Framework_TestCase
{
	protected $wikiParser = null;
	
	protected function setUp(){
		$this->wikiParser = new MediaWikiParser("");
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
		$output = $this->wikiParser->parse("''italic''");
		$this->assertEquals("<em>italic</em>", $output);
	}
	
	/**
	 * bold	*bold* 
	 */
	public function testTypefaceBold(){
		$output = $this->wikiParser->parse("'''bold'''");
		$this->assertEquals("<strong>bold</strong>", $output);
	}
	
	/**
	 * bold	*bold* and italic 
	 */
	public function testTypefaceBoldAndItalic(){
		$output = $this->wikiParser->parse("'''''bold & italic'''''");
		$this->assertEquals("<strong><em>bold & italic</strong></em>", $output);
	}
	
	/**
	 * code	`code`
	 */
	public function testTypefaceCodeInline(){
		$output = $this->wikiParser->parse("`code`");
		$this->assertEquals("<tt>code</tt>", $output);
	}	
	

	/**
	 * code	{{{{code}}}
	 */
	public function testTypefaceCodeMultiline(){
		$output = $this->wikiParser->parse("{{{code}}}");
		$this->assertEquals("<tt>code</tt>", $output);
		
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
		$output = $this->wikiParser->parse("'''''bold''' in italic''");
		$this->assertEquals("<strong><em>bold</strong> in italic</em>", $output);
		
		$output = $this->wikiParser->parse("'''''italic'' in bold'''");
		$this->assertEquals("<strong><em>italic</em> in bold</strong>", $output);
	}
	
	/**
	 * Headings
	 */
	public function testHeadings(){
		$output = $this->wikiParser->parse("= Heading 1 =");
		$this->assertEquals("<h1>Heading 1</h1>", $output);
		
		$output = $this->wikiParser->parse("== Heading 2 ==");
		$this->assertEquals("<h2>Heading 2</h2>", $output);
		
		$output = $this->wikiParser->parse("=== Heading 3 ===");
		$this->assertEquals("<h3>Heading 3</h3>", $output);
		
		$output = $this->wikiParser->parse("==== Heading 4 ====");
		$this->assertEquals("<h4>Heading 4</h4>", $output);
		
		$output = $this->wikiParser->parse("===== Heading 5 =====");
		$this->assertEquals("<h5>Heading 5</h5>", $output);		
		
		$output = $this->wikiParser->parse("====== Heading 6 ======");
		$this->assertEquals("<h6>Heading 6</h6>", $output);		
	}
	
	/**
	 * Dividers - four ore more dashes on a single line 
	 */
	public function testDividers(){
		$output = $this->wikiParser->parse("----");
		$this->assertEquals("<hr />", $output);
		
		$output = $this->wikiParser->parse("Random words and ---");
		$this->assertEquals("Random words and ---", $output);
		
		$output = $this->wikiParser->parse("----------------------------------------");
		$this->assertEquals("<hr />", $output);		
	}
	
	/**
	 * Lists http://code.google.com/p/support/wiki/WikiSyntax#Lists 
	 */
	public function testLists(){
		$input_string = <<<HEREDOC

* Start each line
* with an asterisk (*).
** More asterisks gives deeper
*** and deeper levels.
* Line breaks<br/>don't break levels.
*** But jumping levels creates empty space.
Any other start ends the list.
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		$output = str_replace(array('<p>', '</p>'), '', $output);
		
		$expected_output = <<<HEREDOC
<ul>
	<li>
		Start each line
	</li>
	<li>
		with an asterisk (*).
		<ul>
			<li>More asterisks gives deeper
				<ul>
					<li>and deeper levels.</li>
				</ul>
			</li>
		</ul>
	</li>
	<li>Line breaks<br/>
			don't break levels.
		<ul>
			<li>
				But jumping levels creates empty space.
			</li>
		</ul>
	</li>
</ul>
Any other start ends the list.
HEREDOC;
		$expected_output = str_replace(array(chr(13), chr(10), chr(9)), '', $expected_output);
		// $expected_output = preg_replace("/\n(.+)/", '<p>$1</p>', $expected_output);		
		$this->assertEquals($expected_output, $output);

		$output = $this->wikiParser->parse('How about * list in the middle of text');
		$this->assertEquals("How about * list in the middle of text", $output);		
	}
	
	/**
	 * Block quotes are created by indenting a paragraph by at least one space 
	 */
	public function testBlockQuotes(){
		$input_string = <<<HEREDOC

Someone once said:

 This sentence will be quoted in the future as the canonical example
 of a quote that is so important that it should be visually separate
 from the rest of the text in which it appears.
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		
		$expected_output = <<<HEREDOC
<p>Someone once said:</p><p><blockquote> This sentence will be quoted in the future as the canonical example of a quote that is so important that it should be visually separate from the rest of the text in which it appears.</blockquote></p>
HEREDOC;
		
		$this->assertEquals($expected_output, $output);
	}
}
