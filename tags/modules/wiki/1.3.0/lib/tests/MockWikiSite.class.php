<?php
require_once('..\WikiSite.interface.php');

class MockWikiSite implements WikiSite {
	
	public function currentUserCanCreateContent() {
			return true;
		}
		
	public function documentExists($document_name) {
			return $document_name;
		}

	public function getFullLink($document_name) {
		return $document_name;
	}
}