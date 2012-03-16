<?php
require_once ('SyntaxParser.interface.php'); 
require_once ('ParserBase.class.php'); 

/**
 * @brief Converts Google Wiki Syntax to HTML
 * @developer Corina Udrescu (xe_dev@arnia.ro)
 */
class GoogleCodeWikiParser extends ParserBase
{
	/**
	 * @brief Constructor
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $wiki_site  WikiSite
	 * @return
	 */
	public function __construct($wiki_site) 
	{
		parent::__construct($wiki_site);
	}
}
/* End of file GoogleCodeWikiParser.class.php */
/* Location: GoogleCodeWikiParser.class.php */
