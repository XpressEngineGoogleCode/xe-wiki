<?php

require_once('SyntaxParser.interface.php');
require_once('markdown.php');

class MarkdownParser implements SyntaxParser {
	private $wiki_site = null;
	
	public function __construct($wiki_site){
		$this->wiki_site = $wiki_site;
	}

	public function parse($text) {
		$new_text = Markdown($text);
		$new_text = $this->parseLinks($new_text);
		return $new_text;
	}
	
	private function parseLinks($text){
		$text = preg_replace_callback("/
										([<]a		# Starts with 'a' HTML tag
										.*			# Followed by any number of chars
										href[=]		# Then by href=
										[\"']?		# Optional quotes
										(.*?)		# The alias (backreference 1)
										[\"']?		# Optional quotes
										[ >])		# Ends with space or close tag
										(.*?)		# Anchor value
										[<][\/][a][>]			# Ends with a close tag
										/ix", array($this, "_handle_link"), $text);
		return $text;
	}
	
	private function _handle_link($matches){
		$url = $matches[2];
		
		// If external URL, just return it as is
		if(preg_match("/^(https?|ftp|file)/", $url)){
			// return "<a href=$url$local_anchor>" . ($description ? $description : $url) . "</a>";
			return $matches[0];
		}
		
		// If local document that  exists, return expected link and exit
		if($alias = $this->wiki_site->documentExists($url)){
			$full_url = $this->wiki_site->getFullLink($alias);
			$anchor = str_replace($url, $full_url, $matches[0]);
			return $anchor;
		}
		
		// Else, if document does not exist
		//   If user is not allowed to create content, return plain text
		if(!$this->wiki_site->currentUserCanCreateContent()) return $url;
		//   Else return link to create new page
		$full_url = $this->wiki_site->getFullLink($url);
		$description = $matches[3];
		return "<a href=$full_url class=notexist>" . $description . "</a>";		
	}
}