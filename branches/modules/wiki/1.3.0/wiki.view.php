<?php
/**
 * @class  wikiView
 * @author haneul (haneul0318@gmail.com)
 * @brief  wiki module View class
 **/
class wikiView extends wiki 
{
		var $list;
		var $search_option = array('title', 'content', 'title_content', 'comment', 'user_name', 'nick_name', 'user_id', 'tag');
		var $document_exists = array();

		/**
		* @brief Class initialization
		**/
		function init() 
		{
			/**
			* Set the path to skins folder
			* If current selected skin does not exist, fallback to default skin: xe_wiki
			* */
			$template_path = sprintf("%sskins/%s/", $this->module_path, $this->module_info->skin);
			if (!is_dir($template_path) || !$this->module_info->skin) {
				$this->module_info->skin = 'xe_wiki';
				$template_path = sprintf("%sskins/%s/", $this->module_path, $this->module_info->skin);
			}
			$this->setTemplatePath($template_path);

			$oModuleModel = &getModel('module');

			$document_config = $oModuleModel->getModulePartConfig('document', $this->module_info->module_srl);
			if(!isset($document_config->use_history)) $document_config->use_history = 'N';
			$this->use_history = $document_config->use_history;
			Context::set('use_history', $document_config->use_history);

			Context::addJsFile($this->module_path.'tpl/js/wiki.js');

			Context::set('grant', $this->grant);
			Context::set('langs', Context::loadLangSupported());
			
			$editor_config = $oModuleModel->getModulePartConfig('editor', $this->module_info->module_srl);
			if($this->module_info->markup_type != 'xe_wiki_markup'){
				$editor_config->editor_skin = 'xpresseditor';
				$editor_config->content_style = 'default';
				
				$oModuleController = &getController('module');
				$oModuleController->insertModulePartConfig('editor', $this->module_info->module_srl, $editor_config);
			}
		}
		


		/**
		* @brief Posts selected output
		**/
		function dispWikiContent()
		{	
			$output = $this->dispWikiContentView();
			if(!$output->toBool()) return;
		}

		
		/**
		* @brief Display the history of the particular wiki page
		*/
		function dispWikiHistory() {
			$oDocumentModel = &getModel('document');
			$document_srl = Context::get('document_srl');
			$page = Context::get('page');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			if(!$oDocument->isExists()) return $this->stop('msg_invalid_request');
			$entry = $oDocument->getTitleText();
			Context::set('entry',$entry);
			$output = $oDocumentModel->getHistories($document_srl, 10, $page);
			if(!$output->toBool() || !$output->data) 
			{
				Context::set('histories', array());
			}
			else {
				Context::set('histories',$output->data);
				Context::set('page', $output->page);
				Context::set('page_navigation', $output->page_navigation);
			}
			
			Context::set('oDocument', $oDocument);
			
			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}
			
			$this->setTemplateFile('histories');
		}


		/**
		* @brief Document editing screen
		*/
		function dispWikiEditPage() {
			if(!$this->grant->write_document) return $this->dispWikiMessage('msg_not_permitted');

			$oDocumentModel = &getModel('document');
			$document_srl = Context::get('document_srl');
			$entry = Context::get('entry');
			if (!$document_srl) {
				$mid = Context::get('mid');
				$document_srl = $oDocumentModel->getDocumentSrlByAlias($mid, $entry);
			}
			$oDocument = $oDocumentModel->getDocument(0, $this->grant->manager);
			$oDocument->setDocument($document_srl);
			if (!Mobile::isFromMobilePhone() && $this->module_info->markup_type == 'xe_wiki_markup') $oDocument->variables['content'] = nl2br($oDocument->getContentText());
			
			$oDocument->add('module_srl', $this->module_srl);
			if($oDocument->isExists()){
				$oDocument->add('alias', $oDocumentModel->getAlias($document_srl));
			}
			else {
				$oDocument->add('title', $entry);
				$alias = $this->beautifyEntryName($entry);
				$oDocument->add('alias', $alias);
			}
			Context::set('document_srl',$document_srl);
			Context::set('oDocument', $oDocument);
			$history_srl = Context::get('history_srl');
			if($history_srl)
			{
				$output = $oDocumentModel->getHistory($history_srl);
				if($output && $output->content != null)
				{
					Context::set('history', $output);
				}
			} 
			Context::addJsFilter($this->module_path.'tpl/filter', 'insert.xml');
			
			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}			
			
			$this->setTemplateFile('write_form');
		}


		/**
		* @brief Displaying Message 
		**/
		function dispWikiMessage($msg_code) {
			$msg = Context::getLang($msg_code);
			if(!$msg) $msg = $msg_code;
			Context::set('message', $msg);
			$this->setTemplateFile('message');
		}


		/**
		* @brief View a list wiki's articles
		*/
		function dispWikiTitleIndex() {
			$page = Context::get('page');
			$oDocumentModel = &getModel('document');
			$obj->module_srl = $this->module_info->module_srl;
			$obj->sort_index = 'update_order';
			$obj->page = $page;
			$obj->list_count = 50;

			$obj->search_keyword = Context::get('search_keyword');
			$obj->search_target = Context::get('search_target');
			$output = $oDocumentModel->getDocumentList($obj);
			$title_count = count($output->data);
			for($i = 1; $i <= $title_count; $i++)
			{
				$alias = $oDocumentModel->getAlias($output->data[$i]->document_srl);
				$output->data[$i]->add('alias', $alias);
			}

			Context::set('document_list', $output->data);
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);
			Context::set('page', $output->page);
			Context::set('page_navigation', $output->page_navigation);

			// search options settings
			foreach($this->search_option as $opt) $search_option[$opt] = Context::getLang($opt);
			Context::set('search_option', $search_option);
			
			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}
			
			$this->setTemplateFile('title_index');
		}


		/**
		* @brief hierarchical view of the appropriate wiki
		*/
		function dispWikiTreeIndex() {
			$oWikiModel = &getModel('wiki');
			Context::set('document_tree', $oWikiModel->readWikiTreeCache($this->module_srl));
			
			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}
						
			$this->setTemplateFile('tree_list');
		}


		/**
		* @brief Display screen for changing the hierarchy
		*/
		function dispWikiModifyTree() {
			if(!$this->grant->write_document) return new Object(-1,'msg_not_permitted');
			Context::set('isManageGranted', $this->grant->write_document?'true':'false');
			
			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}
			
			$this->setTemplateFile('modify_tree');
		}


		/**
		* @brief View update history
		*/
		function addToVisitLog($entry) {
			$module_srl = $this->module_info->module_srl;
			if(!$_SESSION['wiki_visit_log'])
			{
				$_SESSION['wiki_visit_log'] = array();
			}
			if(!$_SESSION['wiki_visit_log'][$module_srl] || !is_array($_SESSION['wiki_visit_log'][$module_srl]))
			{
				$_SESSION['wiki_visit_log'][$module_srl] = array(); 
			}
			else
			{
				foreach($_SESSION['wiki_visit_log'][$module_srl] as $key => $value)
				{
					if($value == $entry)
					{
						unset($_SESSION['wiki_visit_log'][$module_srl][$key]);
					}
				}
				
				if(count($_SESSION['wiki_visit_log'][$module_srl]) >= 5)
				{
					array_shift($_SESSION['wiki_visit_log'][$module_srl]);
				}
			}
			$_SESSION['wiki_visit_log'][$module_srl][] = $entry;
		}


		/**
		* @brief Wiki document output
		* Input: entry or document_srl
		* Output: oDocument and alias
		*	oDocument must have title set
		*/
		function dispWikiContentView() {
			$oWikiModel = &getModel('wiki');
			$oDocumentModel = &getModel('document');

			// The requested order parameter values
			$document_srl = Context::get('document_srl');
			$entry = Context::get('entry');

			if (!$document_srl) {
				if (!$entry) {
					$root = $oWikiModel->getRootDocument($this->module_info->module_srl);
					$document_srl = $root->document_srl;
					$entry = $oDocumentModel->getAlias($document_srl);
					Context::set('entry', $entry);
				}
				$document_srl =  $oDocumentModel->getDocumentSrlByAlias($this->module_info->mid, $entry);
				if(!$document_srl) 	$document_srl = $oDocumentModel->getDocumentSrlByTitle($this->module_info->module_srl, $entry);
			}
		
			/**
			* Check if exists document_srl for requested document
			**/
			if($document_srl) {
				$oDocument = $oDocumentModel->getDocument($document_srl);

				if($oDocument->isExists()) {
					$this->_handleWithExistingDocument($oDocument);

					list($prev_document_srl, $next_document_srl) = $oWikiModel->getPrevNextDocument($this->module_srl, $document_srl);
					if($prev_document_srl) Context::set('oDocumentPrev', $oDocumentModel->getDocument($prev_document_srl));
					if($next_document_srl) Context::set('oDocumentNext', $oDocumentModel->getDocument($next_document_srl));
					$this->addToVisitLog($entry);

				} else {
					Context::set('document_srl','',true);
					return new Object(-1, 'msg_not_founded');
				}
			// generate an empty document object if you do not have a document_srl for requested document
			}
			else
			{
				$oDocument = $oDocumentModel->getDocument(0);
			}
			
			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}
			
			// View posts by checking permissions or display an error message if you do not have permission
			if($oDocument->isExists()) {
				// add page title to the browser title
				Context::addBrowserTitle($oDocument->getTitleText());

				// Increase in hits (if document has permissions)
				if(!$oDocument->isSecret() || $oDocument->isGranted()) $oDocument->updateReadedCount();

				// Not showing the content if it is secret
				if($oDocument->isSecret() && !$oDocument->isGranted()) $oDocument->add('content',Context::getLang('thisissecret'));
				$this->setTemplateFile('view_document');

				// set contributors
				if($this->use_history)
				{
					$oModel = &getModel('wiki');
					$contributors = $oModel->getContributors($oDocument->document_srl);
					Context::set('contributors', $contributors);
				}

				// If the document has no rights set for comments is forced to use
				if($this->module_info->use_comment != 'N') $oDocument->add('allow_comment','Y');
				// Set up alias
				$alias = $oDocumentModel->getAlias($oDocument->document_srl);
				$oDocument->add('alias', $alias);			
			}
			else
			{
				$oDocument->add('title', $entry);
				$alias = $this->beautifyEntryName($entry);
				$oDocument->add('alias', $alias);
				$this->setTemplateFile('create_document');		
			}
			Context::set('visit_log', $_SESSION['wiki_visit_log'][$this->module_info->module_srl]);
			// Setting a value oDocument for being use in skins
			Context::set('oDocument', $oDocument);
			Context::set('entry', $alias);
			
			// Adding javascript filter 
			Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
			// Redirect to user friendly URL if request comes from Search
			
			// get translated language for current document
			$translatedlangs = $oDocument->getTranslationLangCodes();
			$arr_translation_langs = array();
			foreach($translatedlangs as $langs)
			{
				$arr_translation_langs[] = $langs->lang_code;
			}
			Context::set("translatedlangs", $arr_translation_langs);
			
			$this->getBreadCrumbs((int)$oDocument->document_srl);
			
			$error_return_url = Context::get('error_return_url');
			if(isset($error_return_url)) {
				$site_module_info = Context::get('site_module_info');
				if($document_srl)
					$url = getSiteUrl($site_module_info->document,''
										,'mid',$this->module_info->mid
										,'entry',$oDocument->get('alias'));
				else
					$url = getSiteUrl($site_module_info->document,''
							,'mid',$this->module_info->mid
							,'entry',$entry);

				$this->setRedirectUrl($url);			
			}

			return new Object();
		}


		/**
		* @brief Display screen for Post a comment
		**/
		function dispWikiReplyComment() {
			// Check permission
			if(!$this->grant->write_comment) return $this->dispWikiMessage('msg_not_permitted');

			// Produces a list of variables needed to implement
			$parent_srl = Context::get('comment_srl');

			// Return message error if there is no parent_srl
			if(!$parent_srl) return new Object(-1, 'msg_invalid_request');

			// Look for the comment
			$oCommentModel = &getModel('comment');
			$oSourceComment = $oCommentModel->getComment($parent_srl, $this->grant->manager);

			// If there is no reply error
			if(!$oSourceComment->isExists()) return $this->dispWikiMessage('msg_invalid_request');
			if(Context::get('document_srl') && $oSourceComment->get('document_srl') != Context::get('document_srl')) return $this->dispWikiMessage('msg_invalid_request');

			// Generate the target comment
			$oComment = $oCommentModel->getComment();
			$oComment->add('parent_srl', $parent_srl);
			$oComment->add('document_srl', $oSourceComment->get('document_srl'));

			// Set the necessary informations
			Context::set('oSourceComment',$oSourceComment);
			Context::set('oComment',$oComment);

			/** 
			* Add javascript filter
			**/
			Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
			
			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}
			
			$this->setTemplateFile('comment_form');
		}


		/**
		* @brief Modify Comment form output
		**/
		function dispWikiModifyComment() {
			// Check persmission
			if(!$this->grant->write_comment) return $this->dispWikiMessage('msg_not_permitted');

			// Produce a list of variables needed to implement
			$document_srl = Context::get('document_srl');
			$comment_srl = Context::get('comment_srl');

			// If you do not have error for specified comment 
			if(!$comment_srl) return new Object(-1, 'msg_invalid_request');

			// Look for the comment
			$oCommentModel = &getModel('comment');
			$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);

			// If there is no reply error
			if(!$oComment->isExists()) return $this->dispWikiMessage('msg_invalid_request');

			// If the article does not have permission then display the password input screen
			if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');

			// Set the necessary informations
			Context::set('oSourceComment', $oCommentModel->getComment());
			Context::set('oComment', $oComment);

			/** 
			* Add javascript filter
			**/
			Context::addJsFilter($this->module_path.'tpl/filter', 'insert_comment.xml');
			
			$this->setTemplateFile('comment_form');
		}


		/**
		* @brief Delete comment form
		**/
		function dispWikiDeleteComment() {
			// Check permission
			if(!$this->grant->write_comment) return $this->dispWikiMessage('msg_not_permitted');

			// Produce a list of variables needed to implement
			$comment_srl = Context::get('comment_srl');

			// If you do not have error for specified comment
			if($comment_srl) {
				$oCommentModel = &getModel('comment');
				$oComment = $oCommentModel->getComment($comment_srl, $this->grant->manager);
			}

			// If there is no reply error
			if(!$oComment->isExists() ) return $this->dispWikiContent();

			// If the article does not have permission then display the password input screen
			if(!$oComment->isGranted()) return $this->setTemplateFile('input_password_form');

			Context::set('oComment',$oComment);

			/** 
			* Add javascript filter
			**/
			Context::addJsFilter($this->module_path.'tpl/filter', 'delete_comment.xml');

			$this->setTemplateFile('delete_comment_form');
		}
		
		function dispCommentEditor()
        {
            // allow only logged in users to comment.
            if (!Context::get('is_logged'))
            {
                return new Object(-1, 'login_to_comment');
            }

            $document_srl = Context::get('document_srl');

            $oDocumentModel = &getModel("document");
            $oDocument = $oDocumentModel->getDocument($document_srl);

            if (!$oDocument->isExists())
            {
                return new Object(-1, 'msg_invalid_request');
            }

            if (!$oDocument->allowComment())
            {
                return new Object(-1, 'comments_disabled');
            }

            Context::set('oDocument', $oDocument);

            $oModuleModel = &getModel('module');
            $module_info = $oModuleModel->getModuleInfoByModuleSrl($oDocument->get('module_srl'));

            Context::set("module_info", $module_info);

            $module_path = './modules/' . $module_info->module . '/';
            $skin_path = $module_path . 'skins/' . $module_info->skin . '/';

            if(!$module_info->skin || !is_dir($skin_path))
            {
                $skin_path = $module_path . 'skins/xe_wiki/';
            }

            $oTemplateHandler = &TemplateHandler::getInstance();

            $this->add('html', $oTemplateHandler->compile($skin_path, 'comment_form.html'));
        }
		
		//================================================================= private methods

		function _handleWithExistingDocument(&$oDocument)
		{	
			// Display error message if it is a different module than requested module
			if($oDocument->get('module_srl')!=$this->module_info->module_srl ) return $this->stop('msg_invalid_request');

			// Check if you have administrative authority to grant
			if($this->grant->manager) $oDocument->setGrant();

			// Check if history is enable and get Document history otherwise ignore it
			$history_srl = Context::get('history_srl');
			if($history_srl)
			{
                $oDocumentModel = &getModel('document');
				$output = $oDocumentModel->getHistory($history_srl);
				if($output && $output->content != null)
				{
					Context::set('history', $output);
				}
			} 
			$content = $oDocument->getContent(false, false, false, false);
			$content = $this->_renderWikiContent($oDocument->document_srl, $content);
			$oDocument->add('content', $content);
		}

		/**
		* @brief Wiki syntax is written according to the rendering of the content links(private)
		*/
		function _renderWikiContent($document_srl, $org_content)
		{
			$oCacheHandler = &CacheHandler::getInstance('object', null, true);
			/*
			if($oCacheHandler->isSupport()){
				$object_key = sprintf('%s.%s.php', $document_srl, Context::getLangType());
                $cache_key = $oCacheHandler->getGroupKey('wikiContent', $object_key);
				$content = $oCacheHandler->get($cache_key);				
			}
			 * */
            if (!$content)
            {
				// Parse wiki syntax
				if($this->module_info->markup_type == 'googlecode_markup'){
					require_once($this->module_path . "lib/GoogleCodeWikiParser.class.php");
					$wiki_syntax_parser = new GoogleCodeWikiParser;
					$org_content = $wiki_syntax_parser->parse($org_content);
				}
				else if($this->module_info->markup_type == 'mediawiki_markup'){
					require_once($this->module_path . "lib/MediaWikiParser.class.php");
					$wiki_syntax_parser = new MediaWikiParser;
					$org_content = $wiki_syntax_parser->parse($org_content);
				}				
				
                $content = preg_replace_callback("!\[([^\]]+)\]!is", array( $this, 'callback_check_exists' ), $org_content );
                /*
				$entries = array_keys($this->document_exists);
			
				if(count($entries))
				{ 
						$args->entries = "'" . implode("','", $entries) . "'";;
					$args->module_srl = $this->module_info->module_srl;
					$output = executeQueryArray("wiki.getDocumentsWithEntries", $args);

					if($output->data)
					{ 
						foreach($output->data as $alias)
						{ 
							$this->document_exists[$alias->alias_title] = 1;
						} 
					}
				}
				//$content = preg_replace_callback("!\[([^\]]+)\]!is", array(&$this, 'callback_wikilink' ), $content );
				//$content = preg_replace('@<([^>]*)(src|href)="((?!https?://)[^"]*)"([^>]*)>@i','<$1$2="'.Context::getRequestUri().'$3"$4>', $content);
				*/
				
				if($oCacheHandler->isSupport()) $oCacheHandler->put($cache_key, $content);
			}
			
			return $content;
		}


		/**
		* @brief Wiki syntax checking for the presence of linked documents.
		*/
		function callback_check_exists($matches)
		{
			$entry_name = wiki::makeEntryName($matches);
			$this->document_exists[$entry_name->link_entry] = 0;

			return $matches[0];
		}


		/**
		* @brief Linked wiki article link exists by checking the return of the CSS class
		*/
		function getCSSClass($name)
		{
			if($this->document_exists[$name]) return "exists";

			else return "notexist";
		}


		/**
		* @brief The return link to be substituted according to wiki
		*/
		function callback_wikilink($matches)
		{
			if($matches[1]{0} == "!") return "[".substr($matches[1], 1)."]";

			$entry_name = wiki::makeEntryName($matches);
			
			// If document exists, create link with alias -> the title will be correctly retireved from the database
			// Otherwise, use title as entry, so that doc title can be retrieved form URL
			if($this->document_exists[$entry_name->link_entry]) $alias = $entry_name->link_entry;
			else $alias = $entry_name->printing_name;
			
			$answer = "<a href=\"".getFullUrl('', 'mid', $this->mid, 'entry', $alias, 'document_srl', '')."\" class=\"".$this->getCSSClass($entry_name->link_entry)."\" >".$entry_name->printing_name."</a>";

			return $answer;
		}
		
		/*
		* @brief Set list for Tree menu on left side of pages
		*/
		function _loadSidebarTreeMenu($module_srl, $document_srl)
		{
			if($document_srl)
			{
				$oWikiModel = &getModel('wiki');
				$this->list = $oWikiModel->getMenuTree($module_srl, $document_srl, $this->module_info->mid);
			}
			Context::set("list", $this->list);
		}
		
		/*
		* @brief Generate Left menu according with settings from admin panel
		* 
		*/
		function getLeftMenu()
		{
			$oWikiModel = &getModel("wiki");
			$oDocumentModel = &getModel("document");
			$module_srl=$this->module_info->module_srl;
			if ( $this->module_info->menu_style == "classic" || !isset($this->module_info->menu_style) )
			{
				$this->list = $oWikiModel->loadWikiTreeList($module_srl);
				Context::set('list',$this->list);
			}
			else
			{
				
				$document_srl = Context::get("document_srl");
				$entry = Context::get("entry");
				if(!$document_srl) {
					if (!$entry) {
						$root = $oWikiModel->getRootDocument($module_srl);
						$document_srl = $root->document_srl;
						$entry = $oDocumentModel->getAlias($document_srl);
						Context::set('entry', $entry);
					}
					else
					{
						if(is_null($oDocumentModel->getDocumentSrlByTitle($this->module_info->module_srl, $entry)))
						{
							$root = $oWikiModel->getRootDocument($module_srl);
							$document_srl = $root->document_srl;
							$this->_loadSidebarTreeMenu($module_srl, $document_srl);
						}
						else
							$this->_loadSidebarTreeMenu($module_srl, $oDocumentModel->getDocumentSrlByAlias($module_srl, $entry));
					}
					$document_srl = $oDocumentModel->getDocumentSrlByAlias($this->module_info->mid, $entry);
					$this->_loadSidebarTreeMenu($module_srl, $document_srl);
				}
				else
				{
					$this->_loadSidebarTreeMenu($module_srl, $document_srl);
				}
			}
		}
		
		/*
		* @brief Generate Breadcrumbs
		* 
		*/
		function getBreadCrumbs($document_srl)
		{
			// get Breadcrumbs menu
			$oWikiModel = &getModel("wiki");
			$menu_breadcrumbs = $oWikiModel->getBreadcrumbs($document_srl,$this->list);
			Context::set('breadcrumbs',$menu_breadcrumbs);
		}
		
		/**
		* @brief View dor displaying search results
		*/
		function dispWikiSearchResults()
		{
			$oWikiModel = &getModel('wiki');
			$oDocumentModel = &getModel('document');
			$oModuleModel = &getModel('module');

			$moduleList = $oWikiModel->getModuleList(true);
			$moduleList = $this->_sortArrayByKeyDesc($moduleList, 'search_rank');
			Context::set('module_list', $moduleList);

			$target_mid = $this->module_info->module_srl;
			$is_keyword = Context::get("search_keyword");

			$this->_searchKeyword($target_mid, $is_keyword);

			// set tree menu for left side of page
			if(isset($this->module_info->with_tree) && $this->module_info->with_tree)
			{
				$this->getLeftMenu();
			}
			$this->setTemplateFile('document_search');
		}
		
		/**
		* @brief Sorts array descending by key
		* // TODO See if can be removed and replaced with a query
		*/
		function _sortArrayByKeyDesc($object_array, $key ){
			$key_array = array();
			foreach($object_array as $obj ){
					$key_array[$obj->{$key}] = $obj;
			}

			krsort($key_array);

			$result = array();
			foreach($key_array as $rank => $obj ){
					$result[] = $obj;
			}
			return $result;
		}
		
		/**
		* @brief Adds info to document - user friendly url and others
		* for pretty displaying in search results
		* // TODO See if it can be replaced / removed
		*/
		function _resolveDocumentDetails($oModuleModel, $oDocumentModel, $doc){

			$entry = $oDocumentModel->getAlias($doc->document_srl);

			$module_info = $oModuleModel->getModuleInfoByDocumentSrl($doc->document_srl);
			$doc->browser_title = $module_info->browser_title;
			$doc->mid = $module_info->mid;


			if ( isset($entry) ){
					$doc->entry = $entry;
			}else{
					$doc->entry = "bugbug";
			}
		}

		/**
		* @brief Helper method for search
		*/
		function _searchKeyword($target_mid, $is_keyword){
			$page =  Context::get('page');
			if (!isset($page)) $page = 1;

			$search_target = Context::get('search_target');
			if(isset($search_target)){
					if ($search_target == 'tag') $search_target = 'tags';
			}
			$oWikiModel = &getModel('wiki');
			$oModuleModel = &getModel('module');
			$oDocumentModel = &getModel('document');


			$output = $oWikiModel->search($is_keyword, $target_mid, $search_target, $page, 10);

			if($output->data)
			foreach($output->data as $doc){
					$this->_resolveDocumentDetails($oModuleModel, $oDocumentModel, $doc);
			}

			Context::set('document_list', $output->data);
			Context::set('total_count', $output->total_count);
			Context::set('total_page', $output->total_page);

			Context::set('page', $page);
			Context::set('page_navigation', $output->page_navigation);

			return $output;
		}
}
?>
