<?php

require_once('MockWikiSite.class.php');
require_once('../GoogleCodeWikiParser.class.php');

class GoogleCodeWikiParserTest extends PHPUnit_Framework_TestCase
{
	protected $wikiParser = null;
	
	protected function setUp(){
		$this->wikiParser = new GoogleCodeWikiParser(new MockWikiSite());
	}

	/**
	 * #summary	 One-line summary of the page 
	 */
	function testPragmasSummary()
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
	function testPragmasLabels(){
		$this->markTestSkipped("Label support was not implemented");
	}
	
	/**
	 * #sidebar	 See Side navigation http://code.google.com/p/support/wiki/WikiSyntax#Side_navigation
	 */
	function testPragmasSidebar(){
		$this->markTestSkipped("Sidebar support was not implemented");
	}
	
	/**
	 * Paragraphs - Use one or more blank lines to separate paragraphs.
	 */
	function testParagraphs(){
        $this->markTestSkipped("doesn't add <p> wrapper anymore.");
        $output = $this->wikiParser->parse("\nA paragraph");
		$this->assertEquals("<p>A paragraph</p>", $output);
	} 
	
	/**
	 * italic	_italic_ 
	 */
	function testTypefaceItalic(){
		$output = $this->wikiParser->parse("_italic_");
		$this->assertEquals("<em>italic</em>", $output);
	}
	
	/**
	 * bold	*bold* 
	 */
	function testTypefaceBold(){
		$output = $this->wikiParser->parse("*bold*");
		$this->assertEquals("<strong>bold</strong>", $output);
	}
	
	/**
	 * code	`code`
	 */
	function testTypefaceCodeInline(){
		$output = $this->wikiParser->parse("`code`");
		$this->assertEquals("<tt>code</tt>", $output);
	}	
	

	/**
	 * code	{{{{code}}}
	 */
	function testTypefaceCodeMultiline(){
        $this->markTestSkipped("no <br> in <pre>");
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
	function testTypefaceSuperscript(){
		$output = $this->wikiParser->parse("^super^script");
		$this->assertEquals("<sup>super</sup>script", $output);
	}			
	
	/**
	 * subscript	,,sub,,script
	 */
	function testTypefaceSubscript(){
		$output = $this->wikiParser->parse(",,sub,,script");
		$this->assertEquals("<sub>sub</sub>script", $output);
	}				
	
	/**
	 * strikeout ~~strikeout~~
	 */
	function testTypefaceStrikeout(){
		$output = $this->wikiParser->parse("~~strikeout~~");
		$this->assertEquals("<span style='text-decoration:line-through'>strikeout</span>", $output);
	}					
	
	/**
	 * Mixed typeface styles 
	 */
	function testTypefaceCombinations(){
		$output = $this->wikiParser->parse("_*bold* in italics_");
		$this->assertEquals("<em><strong>bold</strong> in italics</em>", $output);
		
		$output = $this->wikiParser->parse("*_italics_ in bold*");
		$this->assertEquals("<strong><em>italics</em> in bold</strong>", $output);

		$output = $this->wikiParser->parse("*~~strike~~ works too*");
		$this->assertEquals("<strong><span style='text-decoration:line-through'>strike</span> works too</strong>", $output);
		
		$output = $this->wikiParser->parse("~~as well as _this_ way round~~");
		$this->assertEquals("<span style='text-decoration:line-through'>as well as <em>this</em> way round</span>", $output);		
	}
	
	/**
	 * Headings
	 */
	function testHeadings(){
		$output = $this->wikiParser->parse("= Heading 1 =", false);
		$this->assertEquals("<h1>Heading 1</h1>", $output);
		
		$output = $this->wikiParser->parse("== Heading 2 ==", false);
		$this->assertEquals("<h2>Heading 2</h2>", $output);
		
		$output = $this->wikiParser->parse("=== Heading 3 ===", false);
		$this->assertEquals("<h3>Heading 3</h3>", $output);
		
		$output = $this->wikiParser->parse("==== Heading 4 ====", false);
		$this->assertEquals("<h4>Heading 4</h4>", $output);
		
		$output = $this->wikiParser->parse("===== Heading 5 =====", false);
		$this->assertEquals("<h5>Heading 5</h5>", $output);		
		
		$output = $this->wikiParser->parse("====== Heading 6 ======", false);
		$this->assertEquals("<h6>Heading 6</h6>", $output);		
	}
	
	/**
	 * Dividers - four ore more dashes on a single line 
	 */
	function testDividers(){
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
	function testLists(){
		$input_string = <<<HEREDOC
The following is:
  * A list
  * Of bulleted items
    # This is a numbered sublist
    # Which is done by indenting further
  * And back to the main bulleted list

 * This is also a list
 * With a single leading space
 * Notice that it is rendered
  # At the same levels
  # As the above lists.
 * Despite the different indentation levels
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		$expected_output = <<<HEREDOC
The following is:<ul>
<li>A list</li>
<li>Of bulleted items</li><ol>
<li>This is a numbered sublist</li>
<li>Which is done by indenting further</li></ol>
<li>And back to the main bulleted list</li></ul>
<ul>
<li>This is also a list</li>
<li>With a single leading space</li>
<li>Notice that it is rendered</li><ol>
<li>At the same levels</li>
<li>As the above lists.</li></ol>
<li>Despite the different indentation levels</li></ul>
HEREDOC;
		$expected_output = str_replace(chr(13), '', $expected_output);
		$expected_output = preg_replace("/\n(.+)/", '<p>$1</p>', $expected_output);		
		$this->assertEquals($expected_output, $output);

		$output = $this->wikiParser->parse('How about * list in the middle of text');
		$this->assertEquals("How about * list in the middle of text", $output);		
		
		$one_line_list = <<<HEREDOC
Here\'s a one line list:
 * One line list
HEREDOC;
		$output = $this->wikiParser->parse($one_line_list);
		$this->assertEquals("Here\'s a one line list:<ul><p><li>One line list</li></ul></p>", $output);
		

	}
	
	/**
	 * Block quotes are created by indenting a paragraph by at least one space 
	 */
	function testBlockQuotes(){
		$input_string = <<<HEREDOC

Someone once said:

  This sentence will be quoted in the future as the canonical example
  of a quote that is so important that it should be visually separate
  from the rest of the text in which it appears.
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		
		$expected_output = <<<HEREDOC
<p>Someone once said:</p><p><blockquote>  This sentence will be quoted in the future as the canonical example  of a quote that is so important that it should be visually separate  from the rest of the text in which it appears.</blockquote></p>
HEREDOC;
		
		$this->assertEquals($expected_output, $output);
	}
	
	/**
	 * Internal link, automatically convert camel case words to links 
	 */
	function testLinks_InternalCamelCase(){
		// Test CamelCase links
		$output = $this->wikiParser->parse("WikiSyntax is identified and linked automatically.");
		$this->assertEquals("<a href=WikiSyntax>WikiSyntax</a> is identified and linked automatically.", $output);
	}
	
	/**
	 * Internal links, denoted by square brackets
	 */
	function testLinks_InternalBracketsSimple(){
		// TODO Check (if possible) that brackets are replaced with links only if user is logged in. Otherwise, escape brackets.
		$output = $this->wikiParser->parse("Wikipage is not identified, so if you have a page named [Wikipage] you need to link it explicitly.");
		$this->assertEquals("Wikipage is not identified, so if you have a page named <a href=Wikipage>Wikipage</a> you need to link it explicitly.",$output);
	}	
	
	/**
	 * Internal links, denoted by square brackets. Contain description given through a space.
	 */
	function testLinks_InternalBracketsWithDescription(){
		$input_string = "If the WikiSyntax page is actually about reindeers, you can provide a
						description, so that people know you are actually linking to a page on
						[WikiSyntax reindeer flotillas].";
		$input_string = str_replace(array(chr(13), chr(10), chr(9)), '', $input_string);
		$expected_output = "
						If the <a href=WikiSyntax>WikiSyntax</a> page is actually about reindeers, you can provide a
						description, so that people know you are actually linking to a page on
						<a href=WikiSyntax>reindeer flotillas</a>.";
		$expected_output = str_replace(array(chr(13), chr(10), chr(9)), '', $expected_output);
		
		$output = $this->wikiParser->parse($input_string);
		$this->assertEquals($expected_output,$output);
	}		
	
	/**
	 * Internal links - escpae words in CamelCase
	 */
	function testLinks_InternalEscapedCamelCase(){
		$input_string = "If you want to mention !WikiSyntax without it being autolinked, use an exclamation mark to prevent linking.";
		$expected_output = "If you want to mention WikiSyntax without it being autolinked, use an exclamation mark to prevent linking.";
		
		$output = $this->wikiParser->parse($input_string);
		$this->assertEquals($expected_output,$output);
	}			
	
	/**
	 * Internal link to page anchor 
	 * TODO Make sure local anchors are automatically generated for headings
	 */
	function testLinks_InternalLocalAnchor(){
		$output = $this->wikiParser->parse("[WikiSyntax#Wiki-style_markup]");
		$this->assertEquals("<a href=WikiSyntax#Wiki-style_markup>WikiSyntax</a>", $output);
		
		$output = $this->wikiParser->parse("[WikiSyntax#Wiki-style_markup Read about style]");
		$this->assertEquals("<a href=WikiSyntax#Wiki-style_markup>Read about style</a>", $output);
	}
	
	/**
	 * Links to issues and revisions
	 * TODO: Setup a field in module admin where you can link wiki to GoogleCode projects, so that issues / revision links will still work 
	 */
	function testLinks_IssuesAndRevisions(){
		$this->markTestSkipped("Work in progress.");
	}
	
	/**
	 * External urls - plain links
	 */
	function testLinks_ExternalsPlain(){
		$output = $this->wikiParser->parse("Plain URLs such as http://www.google.com/ or ftp://ftp.kernel.org/ are automatically made into links.");
		$this->assertEquals("Plain URLs such as <a href=\"http://www.google.com/\">http://www.google.com/</a> or <a href=\"ftp://ftp.kernel.org/\">ftp://ftp.kernel.org/</a> are automatically made into links.",$output);
	}
	
	/**
	 * External urls - bracket with description 
	 */
	function testLinks_ExternalBracketsWithDescription(){
		$output = $this->wikiParser->parse("You can also provide some descriptive text. For example, the following link points to the [http://www.google.com Google home page].");
		$this->assertEquals("You can also provide some descriptive text. For example, the following link points to the <a href=http://www.google.com>Google home page</a>.", $output);
		
	}
	
	/**
	 * External urls - links that point to images 
	 */
	function testImg_PlainURL(){
		$output = $this->wikiParser->parse("If your link points to an image, it will get inserted as an image tag into the page: http://code.google.com/images/code_sm.png");
		$this->assertEquals("If your link points to an image, it will get inserted as an image tag into the page: <img src=http://code.google.com/images/code_sm.png />", $output);
		
		$output = $this->wikiParser->parse("http://chart.apis.google.com/chart?chs=200x125&chd=t:48.14,33.79,19.77|83.18,18.73,12.04&cht=bvg&nonsense=something_that_ends_with.png");
		$this->assertEquals("<img src=http://chart.apis.google.com/chart?chs=200x125&chd=t:48.14,33.79,19.77|83.18,18.73,12.04&cht=bvg&nonsense=something_that_ends_with.png />", $output);
	}
		
	/**
	 * Image links 
	 */
	function testImg_WithLink(){
		$output = $this->wikiParser->parse("[http://code.google.com/ http://code.google.com/images/code_sm.png]");
		$this->assertEquals("<a href=http://code.google.com/><img src=http://code.google.com/images/code_sm.png /></a>", $output);
	}
	
	/**
	 * Tables 
	 */
	function testTables(){
		$input_string = <<<HEREDOC
|| *Year* || *Temperature (low)* || *Temperature (high)* ||
|| 1900 || -10 || 25 ||
|| 1910 || -15 || 30 ||
|| 1920 || -10 || 32 ||
|| 1930 || _N/A_ || _N/A_ ||
|| 1940 || -2 || 40 ||
HEREDOC;
		$expected_output = <<<HEREDOC
<table border=1 cellspacing=0 cellpadding=5><p><tr><td> <strong>Year</strong> </td><td> <strong>Temperature (low)</strong> </td><td> <strong>Temperature (high)</strong> </td></tr></p><p><tr><td> 1900 </td><td> -10 </td><td> 25 </td></tr></p><p><tr><td> 1910 </td><td> -15 </td><td> 30 </td></tr></p><p><tr><td> 1920 </td><td> -10 </td><td> 32 </td></tr></p><p><tr><td> 1930 </td><td> <em>N/A</em> </td><td> <em>N/A</em> </td></tr></p><p><tr><td> 1940 </td><td> -2 </td><td> 40 </td></tr></p><p></table></p>
HEREDOC;
		$output = $this->wikiParser->parse($input_string);
		$this->assertEquals($expected_output, $output);
	}
	
	/**
	 * Escaping special HTML tags 
	 */
	function testHTMLTagEscaping(){
		$output = $this->wikiParser->parse("`<hr>`");
		$this->assertEquals("<tt>&lt;hr&gt;</tt>", $output);
		
		$output = $this->wikiParser->parse("{{{<hr>}}}");
		$this->assertEquals("<tt>&lt;hr&gt;</tt>", $output);		
	}

	/**
	 * Issue 77: Google wiki syntax does not support 'img' tag
	 * http://code.google.com/p/xe-wiki/issues/detail?id=77
	 */
	function testImgTags()
	{
		$output = $this->wikiParser->parse("<img src=\"http://naradesign.net/photo/DSCN0687.JPG\" alt=\"\" />");
		$this->assertEquals("<img src=\"http://naradesign.net/photo/DSCN0687.JPG\" alt=\"\" />", $output);
	}

	/**
	 * Issue 78: Google wiki syntax does not support 'CamelCase' link text.
	 * http://code.google.com/p/xe-wiki/issues/detail?id=78
	 */
	function testCamelCaseLink()
	{
		$output = $this->wikiParser->parse("[http://example.com/ CamelCaseLink]");
		$this->assertEquals("<a href=http://example.com/>CamelCaseLink</a>", $output);
	}

	/**
	 * Issue 81: Google wiki syntax breaks link text of including capital character.
	 * http://code.google.com/p/xe-wiki/issues/detail?id=81
	 */
	function testNormalCaseExternalLink()
	{
		$output = $this->wikiParser->parse("http://example.com/normalcase#anchor");
		$this->assertEquals("<a href=\"http://example.com/normalcase#anchor\">http://example.com/normalcase#anchor</a>", $output);
	}

	/**
	 * Issue 81: Google wiki syntax breaks link text of including capital character.
	 * http://code.google.com/p/xe-wiki/issues/detail?id=81
	 */
	function testNormalCaseCapitalLetterExternalLink()
	{
		$output = $this->wikiParser->parse("http://example.com/Normalcase#anchor");
		$this->assertEquals("<a href=\"http://example.com/Normalcase#anchor\">http://example.com/Normalcase#anchor</a>", $output);
	}

	/**
	 * Issue 81: Google wiki syntax breaks link text of including capital character.
	 * http://code.google.com/p/xe-wiki/issues/detail?id=81
	 */
	function testPascalCaseExternalLink()
	{
		$output = $this->wikiParser->parse("http://example.com/PascalCase#anchor");
		$this->assertEquals("<a href=\"http://example.com/PascalCase#anchor\">http://example.com/PascalCase#anchor</a>", $output);
	}

	/**
	 * Issue 81: Google wiki syntax breaks link text of including capital character.
	 * http://code.google.com/p/xe-wiki/issues/detail?id=81
	 */
	function testCamelCaseExternalLink()
	{
		$output = $this->wikiParser->parse("http://example.com/camelCase#anchor");
		$this->assertEquals("<a href=\"http://example.com/camelCase#anchor\">http://example.com/camelCase#anchor</a>", $output);
	}
}
