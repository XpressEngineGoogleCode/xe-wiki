<?php
	/**
	 * @class wiki
	 * @author NHN (developers@xpressengine.com)
	 * @brief  wiki module high class
	 **/

	require_once("lib\WikiSite.interface.php");

	class wiki extends ModuleObject implements WikiSite {

		static $omitting_characters = array('/&/', '/\//', '/,/', '/ /');
		static $replacing_characters = array('', '', '', '_');

	    public function getWikiTextParser(){
			if($this->module_info->markup_type == 'markdown'){
				require_once($this->module_path . "lib/MarkdownParser.class.php");
				$wiki_syntax_parser = new MarkdownParser($this);					
			}				
			else if($this->module_info->markup_type == 'googlecode_markup'){
				require_once($this->module_path . "lib/GoogleCodeWikiParser.class.php");
				$wiki_syntax_parser = new GoogleCodeWikiParser($this);
			}
			else if($this->module_info->markup_type == 'mediawiki_markup'){
				require_once($this->module_path . "lib/MediaWikiParser.class.php");
				$wiki_syntax_parser = new MediaWikiParser($this);
			}
			else {
				require_once($this->module_path . "lib/XEWikiParser.class.php");
				$wiki_syntax_parser = new XEWikiParser($this);
			}		
			return $wiki_syntax_parser;
		}
		
		static function beautifyEntryName($entry_name)
		{
			$entry_name = strip_tags($entry_name);
			$entry_name = html_entity_decode($entry_name);
			$entry_name = preg_replace(wiki::$omitting_characters, wiki::$replacing_characters, $entry_name);
			$entry_name = preg_replace('/[_]+/', '_', $entry_name);
			$entry_name = strtolower($entry_name);
			
			return $entry_name;			
		}
		
		/**
		 * Checks if a certain document exists
		 * Returns doc_alias if document exists or false otherwise
		 * @param type $document_name
		 * @return boolean 
		 */
		public function documentExists($document_name){
			$oDocumentModel  = &getModel('document');
			// Search for document by alias
			$document_srl =  $oDocumentModel->getDocumentSrlByAlias($this->module_info->mid, $document_name);
			if($document_srl) return $document_name;
			
			// If not found, search by title
			$document_srl = $oDocumentModel->getDocumentSrlByTitle($this->module_info->module_srl, $document_name);			
			if($document_srl) {
				$alias = $oDocumentModel->getAlias($document_srl);
				return $alias;
			}
			
			return false;
		}
		
		public function currentUserCanCreateContent(){
			return $this->grant->write_document;
		}
		
		public function getFullLink($document_name){
			return getUrl('','mid', $this->module_info->mid, 'entry', $document_name);
		}		

		function moduleInstall() {
			return new Object();
		}

		/**
		 * @brief Check if exist a new version
		 **/
		function checkUpdate() {
			$flag = false;
			$flag = $this->_hasOldStyleAliases();
			
			$oDB = DB::getInstance();
			if(!$oDB->isIndexExists("wiki_links","idx_link_doc_cur_doc")) 
					$flag = true;
			
			return $flag;
		}

		/**
		 * @brief Module Updates
		 */
		function moduleUpdate() {
			if($this->_hasOldStyleAliases())
				$this->_updateOldStyleAliases();
			
            // tag in the index column of the table tag
			$oDB = DB::getInstance();
            if(!$oDB->isIndexExists("wiki_links","idx_link_doc_cur_doc")) 
                $oDB->addIndex("wiki_links","idx_link_doc_cur_doc", array("link_doc_srl","cur_doc_srl"));
			
			return new Object(0, 'success_updated');
		}

		/**
		 * @brief uninstall module
		 */
		function moduleUninstall() {
			return new Object();
		}

		/**
		 * @brief Recompile Cache
		 **/
		function recompileCache() {
			$oCacheHandler = &CacheHandler::getInstance('object', null, true);
			if($oCacheHandler->isSupport()){
				$oCacheHandler->invalidateGroupKey("wikiContent");				
			}							
		}

		/**
		 * @brief Make sure that alias does not contain special characters / spaces, etc
		 */
		function _hasOldStyleAliases()
		{
			// Get all Wiki module_srl.
			$output = executeQueryArray('wiki.getAllWikiList', null);
			$wiki_srls = array();
			if(count($output->data))
			{
				foreach($output->data as $key => $module_instance)
				{
					$wiki_srls[] = $module_instance->module_srl;
				}
			}
			$args->wiki_srls = $wiki_srls;

			$output = executeQueryArray('wiki.checkOldStyleAliases', $args);
			if(count($output->data))
			{
				$omitting_characters = array('&','//', ',', ' ');

				foreach($output->data as $key => $doc_alias)
				{
					//if($doc_alias->alias_title == 'Front Page') continue;
					foreach($omitting_characters as $key => $char)
					{	
						if(strpos($doc_alias->alias_title, $char)) return true;
					}
				}
			}
			return false;
		}


		/**
		 * @brief special characters / spaces that have not been removed and fixes alias in a batch
		 */
		function _updateOldStyleAliases()
		{	   
			// Get all Wiki module_srl
			$output = executeQueryArray('wiki.getAllWikiList', null);
			$wiki_srls = array();
			if(count($output->data))
			{	   
				foreach($output->data as $key => $module_instance)
				{	   
					$wiki_srls[] = $module_instance->module_srl;
				}	   
			}	   
			$args->wiki_srls = $wiki_srls;
			$output = executeQueryArray('wiki.checkOldStyleAliases', $args); 

			if(count($output->data))
			{	   
				foreach($output->data as $key => $doc_alias)
				{	   
					$omitting_characters = array('&','//', ',', ' ');
					//if($doc_alias->alias_title == 'Front Page') continue;
					foreach($omitting_characters as $key => $char)
					{	   
						if(strpos($doc_alias->alias_title, $char)) 
						{	   
							unset($args);
							$args->alias_srl = $doc_alias->alias_srl;
							$args->alias_title = wiki::beautifyEntryName($doc_alias->alias_title);
							$output = executeQuery('wiki.updateDocumentAlias', $args); 
						}	   
					}	   
				}	   
			}	   
		}
	}
?>
