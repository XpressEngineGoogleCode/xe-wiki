<?php
require_once ('SyntaxParser.interface.php'); 
require_once ('markdown.php'); 

/**
 * @brief Converts Markdown syntax into HTML using external Markdown library
 * @developer Corina Udrescu (xe_dev@arnia.ro)
 */
class MarkdownParser implements SyntaxParser
{
	private $wiki_site = NULL; 
	
	protected $internal_links_regex = "/
										([<]a		# Starts with 'a' HTML tag
										.*			# Followed by any number of chars
										href[=]		# Then by href=
										[\"']?		# Optional quotes
										(.*?)		# The alias (backreference 1)
										[\"']?		# Optional quotes
										[ >])		# Ends with space or close tag
										(.*?)		# Anchor value
										[<][\/][a][>]			# Ends with a close tag
										/ix"; 
	
	/**
	 * @brief Constructor
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $wiki_site WikiSite
	 * @return
	 */
	public function __construct($wiki_site) 
	{
		$this->wiki_site = $wiki_site;
	}
	
	/**
	 * @brief Receives a string of text written in Markdown and returns HTML
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $text string
	 * @return string
	 */
	public function parse($text) 
	{
		$new_text = Markdown($text); 
		$new_text = $this->parseLinks($new_text); 
		return $new_text;
	}
	
	/**
	 * @brief Returns a list of all internal links in a page, given as a list of aliases
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $text string
	 * @return array 
	 */
	public function getLinkedDocuments($text) 
	{
		$new_text = Markdown($text); 
		
		$matches = array(); 
		$aliases = array(); 
		preg_match_all($this->internal_links_regex, $new_text, &$matches, PREG_SET_ORDER);
		foreach($matches as $match) 
		{
			$url = $match[2];
			
			// If external URL, continue
			if(preg_match("/^(https?|ftp|file)/", $url)) 
			{
				continue; 
			}
			
			$alias = $this->wiki_site->documentExists($url);
			if($alias && !in_array($alias, $aliases)) 
			{
				$aliases[] = $alias;
			}
		}
		return $aliases;
	}
	
	/**
	 * @brief Searches for links in document and replaces them fully qualified urls
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access private
	 * @param $text string
	 * @return string
	 */
	private function parseLinks($text) 
	{
		$text = preg_replace_callback($this->internal_links_regex, array($this, "_handle_link"), $text); 
		return $text;
	}
	
	/**
	 * @brief Callback function for parseLinks 
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access private
	 * @param $matches array
	 * @return string 
	 */
	private function _handle_link($matches) 
	{
		$url = $matches[2];
		
		// If external URL, just return it as is
		if(preg_match("/^(https?|ftp|file)/", $url)) 
		{
			// return "<a href=$url$local_anchor>" . ($description ? $description : $url) . "</a>";
			return $matches[0];
		}
		
		// If local document that  exists, return expected link and exit
		if($alias = $this->wiki_site->documentExists($url)) 
		{
			$full_url = $this->wiki_site->getFullLink($alias); 
			$anchor = str_replace($url, $full_url, $matches[0]); 
			return $anchor;
		}
		
		// Else, if document does not exist
		//   If user is not allowed to create content, return plain text
		if(!$this->wiki_site->currentUserCanCreateContent()) 
		{
			return $url;
		}
		
		//   Else return link to create new page
		$full_url = $this->wiki_site->getFullLink($url); 
		$description = $matches[3]; 
		return "<a href=$full_url class=notexist>" . $description . "</a>";
	}
}
/* End of file MarkdownParser.class.php */
/* Location: MarkdownParser.class.php */