<?php

interface SyntaxParser {
	
	/**
	 * Converts a certain wiki syntax to HTML 
	 */
	public function parse($text);
}
