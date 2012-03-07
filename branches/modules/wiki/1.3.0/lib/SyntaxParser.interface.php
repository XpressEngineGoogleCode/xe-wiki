<?php

interface SyntaxParser {
	
	/**
	 * Converts a certain wiki syntax to HTML 
	 */
	public function parse($text);
	
	/**
	 * Finds all internal links in a text and returns document aliases 
	 */
	public function getLinkedDocuments($text);
}
