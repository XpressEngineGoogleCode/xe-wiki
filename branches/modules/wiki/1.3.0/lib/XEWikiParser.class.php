<?php

/**
 * Syntax parser for XE Wiki
 * Contains the old parsing code of Wiki (before Markdown was made default)
 * Can only be ran in the context of an XE request (requires wiki class, ModuleObject class and Context) 
 */

require_once('SyntaxParser.interface.php');

class XEWikiParser implements SyntaxParser {
	private $wiki_site = null;
	
	public function __construct($wiki_site = null){
		$this->wiki_site = $wiki_site;
	}

	public function parse($org_content) {
		// Replace square brackets with link
		$content = preg_replace_callback("!\[([^\]]+)\]!is", array(&$this, 'callback_wikilink' ), $org_content );
		
		// No idea what this does :)
		$content = preg_replace('@<([^>]*)(src|href)="((?!https?://)[^"]*)"([^>]*)>@i','<$1$2="'.Context::getRequestUri().'$3"$4>', $content);
		return $content;
	}
	
	/**
	* @brief Generate the string to use as entry name
	*/
	function makeEntryName($matches)
	{
		// At first, we assume link does not have description
		$answer->is_alias_link = false;

		$matches[0] = trim($matches[0]);
		$names = explode('|', $matches[1]);
		
		$page_name = trim($names[0]); 
		$link_description = trim($names[1]); 

		if($link_description)
		{
			$answer->is_alias_link = true;
			$answer->printing_name = $link_description;
		}
		else
		{
			$answer->printing_name = $page_name;
		}
		
		$alias = $this->wiki_site->documentExists($page_name);
		if($alias)
		{
			$answer->link_entry = $alias;
			$answer->exists = true;
		}
		else 
		{
			$answer->link_entry = $page_name;
		}
		
		return $answer;
	}	
	
	/**
	* @brief The return link to be substituted according to wiki
	*/
	function callback_wikilink($matches)
	{
		if($matches[1]{0} == "!") return "[".substr($matches[1], 1)."]";

		$entry_name = $this->makeEntryName($matches);

		if($entry_name->exists) {
			$cssClass = 'exist';
		}
		else {
			$cssClass = 'notexist';
		}

		$url = $this->wiki_site->getFullLink($entry_name->link_entry);
		
		$answer = "<a href=\"$url\" class=\"".$cssClass."\" >".$entry_name->printing_name."</a>";

		return $answer;
	}	

}