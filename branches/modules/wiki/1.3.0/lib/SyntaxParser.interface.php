<?php

/**
 *  Interface for a wiki syntax parsers
 * @author Corina Udrescu (xe_dev@arnia.ro)
 */
interface SyntaxParser
{
	/**
	 *  Converts a certain wiki syntax to HTML
	 * @author Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $text string
	 * @return string
	 */
	function parse($text);
	
	/**
	 *  Finds all internal links in a text and returns document aliases
	 * @author Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $text string
	 * @return array Array of document aliases 
	 */
	function getLinkedDocuments($text);
}
/* End of file SyntaxParser.interface.php */
/* Location: SyntaxParser.interface.php */
