<?php

require_once('../MediaWikiParser.class.php');
require_once('MockWikiSite.class.php');

class MediaWikiParserTest extends PHPUnit_Framework_TestCase
{
	protected $wikiParser = null;
	
	protected function setUp(){
		$this->wikiParser = new MediaWikiParser(new MockWikiSite);
	}
	
	private function escapeParserOutput($output){
		$output = str_replace(array(chr(13), chr(10), chr(9)), '', $output);
		return str_replace(array('<p>', '</p>'), '', $output);
	}
	
	private function escapeExpectedOutput($output){
		return str_replace(array(chr(13), chr(10), chr(9)), '', $output);
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
		$output = $this->escapeParserOutput($output);
		
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
		$expected_output = $this->escapeExpectedOutput($output);
		$this->assertEquals($expected_output, $output);

		$output = $this->wikiParser->parse('How about * list in the middle of text');
		$this->assertEquals("How about * list in the middle of text", $output);		
		
		$input_string = <<<HEREDOC

# Start each line
# with a (#).
## More number signs gives deeper
### and deeper
### levels.
# Line breaks<br/>don't break levels.
### But jumping levels creates empty space.
# Blank lines

# end the list and start another.
Any other start also
ends the list.
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		$output = $this->escapeParserOutput($output);
		
		$expected_output = <<<HEREDOC
<ol>
<li>Start each line</li>
<li>with a (#).
<ol>
<li>More number signs gives deeper
<ol>
<li>and deeper</li>
<li>levels.</li>
</ol>
</li>
</ol>
</li>
<li>Line breaks<br/>
don't break levels.
<ol>
<li>
<ol>
<li>But jumping levels creates empty space.</li>
</ol>
</li>
</ol>
</li>
<li>Blank lines</li>
</ol>
<ol>
<li>end the list and start another.</li>
</ol>
<p>Any other start also ends the list.</p>
HEREDOC;
		$expected_output = $this->escapeExpectedOutput($expected_output);
		$this->assertEquals($expected_output, $output);		
	}
	
	public function testDefinitionList(){
		$input_string = <<<HEREDOC

;item 1
: definition 1
;item 2
: definition 2-1
: definition 2-2
HEREDOC;
		$expected_output = <<<HEREDOC
<dl>
<dt>item 1</dt>
<dd> definition 1</dd>
<dt>item 2</dt>
<dd> definition 2-1</dd>
<dd> definition 2-2</dd>
</dl>
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		
		$output = $this->escapeParserOutput($output);
		$expected_output = $this->escapeExpectedOutput($expected_output);
		
		$this->assertEquals($expected_output, $output);
	}
	
	public function testIndentText(){
		$input_string = <<<HEREDOC

: Single indent
:: Double indent
::::: Multiple indent
HEREDOC;
		$expected_output = <<<HEREDOC
<dl>
	<dd>Single indent
	<dl>
		<dd>Double indent
		<dl>
			<dd>
			<dl>
				<dd>
				<dl>
					<dd>Multiple indent</dd>
				</dl>
				</dd>
			</dl>
			</dd>
		</dl>
		</dd>
	</dl>
	</dd>
</dl>
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		
		$output = $this->escapeParserOutput($output);
		$expected_output = $this->escapeExpectedOutput($expected_output);		
		
		$this->assertEquals($expected_output, $output);		
	}
	
	public function testMixtureOfLists(){
		$input_string = <<<HEREDOC
# one
# two
#* two point one
#* two point two
# three
#; three item one
#: three def one
# four
#: four def one
#: this looks like a continuation
#: and is often used
#: instead<br/>of <nowiki><br/></nowiki>
# five
## five sub 1
### five sub 1 sub 1
## five sub 2
HEREDOC;
		$expected_output = <<<HEREDOC
<ol>
	<li>one</li>
	<li>two
		<ul>
			<li>two point one</li>
			<li>two point two</li>
		</ul>
	</li>
	<li>three
		<dl>
			<dt>three item one</dt>
			<dd>three def one</dd>
		</dl>
	</li>
	<li>four
		<dl>
			<dd>four def one</dd>
			<dd>this looks like a continuation</dd>
			<dd>and is often used</dd>
			<dd>instead<br>
			of &lt;br/&gt;</dd>
		</dl>
	</li>
	<li>five
		<ol>
			<li>five sub 1
				<ol>
					<li>five sub 1 sub 1</li>
				</ol>
			</li>
			<li>five sub 2<span id="pre"></span></li>
		</ol>
	</li>
</ol>
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		
		$output = $this->escapeParserOutput($output);
		$expected_output = $this->escapeExpectedOutput($expected_output);
		
		$this->assertEquals($expected_output, $output);				
	}
	
	public function testPreformattedText(){
		$input_string = <<<HEREDOC

 Start each line with a space.
 Text is '''preformatted''' and
 ''markups'' '''''can''''' be done.
HEREDOC;
		$expected_output = <<<HEREDOC
<pre> Start each line with a space.
 Text is <strong>preformatted</strong> and
 <em>markups</em> <strong><em>can</strong></em> be done.
</pre>
HEREDOC;
		$output = $this->wikiParser->parse($input_string);

		$output = $this->escapeParserOutput($output);
		$expected_output = $this->escapeExpectedOutput($expected_output);
		
		$this->assertEquals($expected_output, $output);			
	}
	
	public function testPreformattedTextBlocks(){
		$input_string = <<<HEREDOC

 <nowiki>Start with a space in the first column,
(before the <nowiki>).

Then your block format will be
    maintained.
 
This is good for copying in code blocks:

def function():
    """documentation string"""

    if True:
        print True
    else:
        print False</nowiki>
HEREDOC;
		$expected_output = <<<HEREDOC
<pre class='prettyprint'>Start with a space in the first column,
(before the &lt;nowiki&gt;).

Then your block format will be
    maintained.

 This is good for copying in code blocks:

def function():
    &quot;&quot;&quot;documentation string&quot;&quot;&quot;

    if True:
        print True
    else:
        print False
</pre>
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		
		$output = $this->escapeParserOutput($output);
		$expected_output = $this->escapeExpectedOutput($expected_output);
		
		$this->assertEquals($expected_output, $output);			
	}	
	
	/**
	 * Internal links
	 */
	public function testLinks_InternalSimple(){
		$input_string = "[[Main Page]]";
		$expected_output = "<a href=\"Main_Page\" title=\"Main Page\" class=\"exist\">Main Page</a>";
		
		$output = $this->wikiParser->parse($input_string);
		$this->assertEquals($expected_output,$output);
	}		
	
	/**
	 * Internal links
	 */
	public function testLinks_InternalPiped(){
		$input_string = "[[Main Page|different text]]";
		$expected_output = "<a href=\"Main_Page\" title=\"Main Page\" class=\"exist\">different text</a>";
		
		$output = $this->wikiParser->parse($input_string);
		$this->assertEquals($expected_output,$output);
	}			
	
	/**
	 * Internal links
	 * Short for [[Help:Contents|Contents]]
	 */
	public function testLinks_InternalNamespaceSimple(){
		$input_string = "[[Help:Contents]]";
		$expected_output = "<a href=\"Contents\" title=\"Contents\" class=\"exist\">Contents</a>";
		
		$output = $this->wikiParser->parse($input_string);
		$this->assertEquals($expected_output,$output);
	}			
	
	/**
	 * Internal links
	 */
	public function testLinks_InternalWordEndings(){	
		$output = $this->wikiParser->parse("[[Help]]s");
		$this->assertEquals("<a href=\"Help\" title=\"Help\" class=\"exist\">Helps</a>",$output);
		
		$output = $this->wikiParser->parse("[[Help]]ing");
		$this->assertEquals("<a href=\"Help\" title=\"Help\" class=\"exist\">Helping</a>",$output);		
		 
		$output = $this->wikiParser->parse("[[Help]]anylettersyoulikehere");
		$this->assertEquals("<a href=\"Help\" title=\"Help\" class=\"exist\">Helpanylettersyoulikehere</a>",$output);			
	}				
	
	/**
	 * Internal links
	 */
	public function testLinks_InternalWordEndingsEscape(){	
		$output = $this->wikiParser->parse("[[Help]]<nowiki />ful advice");
		$this->assertEquals("<a href=\"Help\" title=\"Help\" class=\"exist\">Help</a>ful advice",$output);
	}					
	
	/**
	 * Internal links
	 */
	public function testLinks_InternalToAnchor(){	
		$output = $this->wikiParser->parse("[[#See also]]");
		$this->assertEquals("<a href=\"#See_also\" class=\"exist\">#See also</a>",$output);
	}						
	
	/**
	 * Internal links
	 */
	public function testLinks_InternalToAnchorWithDescription(){	
		$output = $this->wikiParser->parse("[[#See also|different text]]");
		$this->assertEquals("<a href=\"#See_also\" class=\"exist\">different text</a>",$output);
	}							

	/**
	 * Internal links
	 */
	public function testLinks_InternalToAnchorOnAnotherPage(){	
		$output = $this->wikiParser->parse("[[Help:Images#See also]]");
		$this->assertEquals("<a href=\"Images#See_also\" title=\"Images\" class=\"exist\">Images#See also</a>",$output);
	}							
	
	/**
	 * External links
	 */
	public function testLinks_ExternalWithoutTag(){	
		$output = $this->wikiParser->parse("http://mediawiki.org");
		$this->assertEquals("<a href=\"http://mediawiki.org\" title=\"http://mediawiki.org\" class=\"external\">http://mediawiki.org</a>",$output);
	}								
	
	/**
	 * External links
	 */
	public function testLinks_ExternalSimple(){	
		$output = $this->wikiParser->parse("[http://mediawiki.org MediaWiki]");
		$this->assertEquals("<a href=\"http://mediawiki.org\" title=\"http://mediawiki.org\" class=\"external\">MediaWiki</a>",$output);
	}									
	
	/**
	 * External links
	 * not supported
	 */
	/*
	public function testLinks_ExternalNumbered(){	
		$output = $this->wikiParser->parse("[http://mediawiki.org]");
		$this->assertEquals("<a href=\"http://mediawiki.org\" title=\"http://mediawiki.org\" class=\"external autonumber\">[1]</a>",$output);
		
		$output = $this->wikiParser->parse("[http://mediawiki.com]");
		$this->assertEquals("<a href=\"http://mediawiki.com\" title=\"http://mediawiki.com\" class=\"external autonumber\">[2]</a>",$output);		
	}
	 * */			
	
	/**
	 * External links
	 */
	public function testLinks_ExternalWithFileIcons(){	
		$output = $this->wikiParser->parse("[http://mediawiki.org MediaWiki]");
		$this->assertEquals("<a href=\"http://mediawiki.org\" title=\"http://mediawiki.org\" class=\"external\">MediaWiki</a>",$output);
	}					
	
	public function testAgrregate1(){
		$input_string = <<<HEREDOC
=== Heading ===
Some content
I would like to add another line

== Subheading ==
Some more content
Some more lines1
:A line with indent
:: A 2-indented line
:: more
:back to 1-indented line
Something here

==== subsub ====
I'm a sub of a sub

=== New heading ===

This is an image.
[[File:http://lygon.net/lygonsoftware.png Lygon Software]]
This is some more text

This is a link: [http://www.google.com Google blaah blaahg].
This is a bold link: '''[http://www.google.com Google]'''.
This is a bold-italic link: '''''[http://www.google.com Google]'''''.
This is '''bold''', '''''bold-italic''''', and ''italic''
This here is a link without text [http://lygon.net]
Don't forget to strip out, or convert any <strong>html</html> if planning on echoing directly to a page.

Some more lines1
:A line with indent
:: A 2-indented line
:: more
:back to 1-indented line

And a hr thingy here
----

== Listings hiar ==

# First
# second
## Second-First
### third
#### z
#### y
#### x
## Second-Second [ftp://www.facebook.com Facebook FTP]
## Second-Third [http://www.google.com Google Here] 
# third
### hax
### lol
#### omg
# wtf

* apple
* orange
** banana
*** cactus
*** dolphin
**** monkey
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		$output = $this->escapeParserOutput($output);
		
		$expected_output = <<<HEREDOC
<h2>Heading</h2>
Some content<br/>
I would like to add another line<br/>

<h1>Subheading</h1>
Some more content<br/>
Some more lines1<br/>
<dl>
<dd>A line with indent</dd>
<dd><dl>
<dd>A 2-indented line</dd> 
<dd>more</dd>
</dl></dd>
<dd>back to 1-indented line</dd>
</dl>
Something here<br/>

<h3>subsub</h3>
I'm a sub of a sub<br/>

<h2>New heading</h2>

This is an image.<br/>
<img src="http://lygon.net/lygonsoftware.png" alt=" Lygon Software"/><br/>
This is some more text<br/>

This is a link: <a href="http://www.google.com">Google blaah blaahg</a>.<br/>
This is a bold link: <strong><a href="http://www.google.com">Google</a></strong>.<br/>
This is a bold-italic link: <strong><em><a href="http://www.google.com">Google</a></em></strong>.<br/>
This is <strong>bold</strong>, <strong><em>bold-italic</em></strong>, and <em>italic</em><br/>
This here is a link without text <a href="http://lygon.net">http://lygon.net</a><br/>

Some more lines1<br/>
<dl>
<dd>A line with indent</dd>
<dd><dl>
<dd>A 2-indented line</dd>
<dd>more</dd>
</dl></dd>
<dd>back to 1-indented line</dd>
</dl>

And a hr thingy here<br/>
<hr/>
<h1>Listings hiar</h1>

<ol>
<li>First</li>
<li>second
<ol>
<li>Second-First
<ol>
<li>third
<ol>
<li>z</li>
<li>y</li>
<li>x</li>
</ol>
</li>
</ol>
</li>
<li>Second-Second <a href="ftp://www.facebook.com">Facebook FTP</a></li>
<li>Second-Third <a href="http://www.google.com">Google Here</a> </li>
</ol>
</li>
<li>third
<ol>
<li>hax</li>
<li>lol
<ol>
<li>omg</li>
</ol>
</li>
</ol>
</li>
<li>wtf</li>
</ol>

<ul>
<li>apple</li>
<li>orange
<ul>
<li>banana
<ul>
<li>cactus</li>
<li>dolphin
<ul>
<li>monkey</li>
</ul>
</li>
</ul>
</li>
</ul>
</li>
</ul>
HEREDOC;
		$expected_output = $this->escapeExpectedOutput($expected_output);
		$this->assertEquals($expected_output, $output);
	} 
	
	
	/**
	 * Tables
	 */
	public function testTables1(){	
		$input_string = <<<HEREDOC
{|
|Orange
|Apple
|-
|Bread
|Pie
|-
|Butter
|Ice cream 
|}
HEREDOC;
		$expected_output = <<<HEREDOC
<table>
<tbody><tr>
<td>Orange</td>
<td>Apple</td>
</tr>
<tr>
<td>Bread</td>
<td>Pie</td>
</tr>
<tr>
<td>Butter</td>
<td>Ice cream </td>
</tr>
</tbody></table>
HEREDOC;
		$expected_output = $this->escapeExpectedOutput($expected_output);
		
		$output = $this->wikiParser->parse($input_string);
		$output = $this->escapeParserOutput($output);
		
		$this->assertEquals($expected_output,$output);
	}			
	
	/**
	 * Tables
	 */
	public function testTables2(){	
		$input_string = <<<HEREDOC
{|
|Orange||Apple||more
|-
|Bread||Pie||more
|-
|Butter||Ice<br />cream||and<br />more
|}
HEREDOC;
		$expected_output = <<<HEREDOC
<table>
<tbody><tr>
<td>Orange</td>
<td>Apple</td>
<td>more</td>
</tr>
<tr>
<td>Bread</td>
<td>Pie</td>
<td>more</td>
</tr>
<tr>
<td>Butter</td>
<td>Ice<br />
cream</td>
<td>and<br />
more</td>
</tr>
</tbody></table>
HEREDOC;
		$expected_output = $this->escapeExpectedOutput($expected_output);		
		
		$output = $this->wikiParser->parse($input_string);
		$output = $this->escapeParserOutput($output);		
		
		$this->assertEquals($expected_output,$output);
	}				
	
}
