<?php

/**
 * @brief Represents a site / app that uses a SyntaxParser
 * @developer Corina Udrescu (xe_dev@arnia.ro)
 */
interface WikiSite
{
	/**
	 * @brief Checks if a document exists based on its title or alias
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $document_name string
	 * @returns string Document alias if exists, or false otherwise
	 */
	public function documentExists($document_name);
	
	/**
	 * @brief Checks if current user is logged in and has permission to add new pages to the wiki
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @return bool
	 */
	public function currentUserCanCreateContent();
	
	/**
	 * @brief Return full link - containg mid information
	 * @developer Corina Udrescu (xe_dev@arnia.ro)
	 * @access public
	 * @param $document_name string Represents document title or alias
	 * @return string
	 */
	public function getFullLink($document_name);
	
}
/* End of file WikiSite.interface.php */
/* Location: WikiSite.interface.php */
