<?php

interface WikiSite {
	/**
	 * Checks if a document exists based on its title or alias
	 * @returns bool
	 */
	public function documentExists($document_name);
	
	/**
	 * Checks if current user is logged in and has permission 
	 * to add new pages to the wiki
	 * @return bool
	 */
	public function currentUserCanCreateContent();
}