<?php
	/**
	 * @class wiki
	 * @author NHN (developers@xpressengine.com)
	 * @brief  wiki module high class
	 **/

	class wiki extends ModuleObject {

		static $omitting_characters = array('/&/', '/\//', '/,/', '/ /');
		static $replacing_characters = array('', '', '', '_');

		/**
		 * @brief Generate the string to use as entry name
		 */
		static function makeEntryName($matches)
		{
			$answer->is_alias_link = false;

			$matches[0] = trim($matches[0]);

			$names = explode('|', $matches[1]);
			foreach ($names as $key => $entry_name)
			{
				$names[$key] = trim($entry_name);
			}
			$processed_names = array();
			foreach ($names as $key => $entry_name)
			{
				$entry_name = wiki::beautifyEntryName($entry_name);
				$processed_names[] = $entry_name;
			}

			if(count($names) == 2)
			{
				$answer->is_alias_link = true;
				$answer->printing_name = $names[1];
				$answer->link_entry = $processed_names[0];
			}
			else
			{
				$answer->printing_name = $names[0];
				$answer->link_entry = $processed_names[0];
			}
			return $answer;
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

		function moduleInstall() {
			return new Object();
		}

		/**
		 * @brief Check if exist a new version
		 **/
		function checkUpdate() {
			$flag = false;
			$flag = $this->_hasOldStyleAliases();
			return $flag;
		}

		/**
		 * @brief Module Updates
		 */
		function moduleUpdate() {
			$this->_updateOldStyleAliases();
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
