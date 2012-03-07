<?php
	/**
	 * @class wikiController
	 * @author NHN (developers@xpressengine.com)
	 * @brief  wiki 모듈의 controller class
	 **/

	class wikiController extends wiki {

		function init() {
		}

		/**
		 * @brief Add a wiki document.
		 */
		function procWikiInsertDocument() {
			// Create object model of document module
			$oDocumentModel = &getModel('document');

			// Create object controller of document module
			$oDocumentController = &getController('document');

			// Check permissions
			if(!$this->grant->write_document) return new Object(-1, 'msg_not_permitted');
			$entry = Context::get('entry');

			// Get required parameters settings
			$obj = Context::getRequestVars();
			$obj->module_srl = $this->module_srl;
			if($this->module_info->use_comment != 'N') $obj->allow_comment = 'Y';
			else $obj->allow_comment = 'N';

			// Set nick_name
			if(!$obj->nick_name)
			{
				$logged_info = Context::get('logged_info');
				if($logged_info) $obj->nick_name = $logged_info->nick_name;
				else $obj->nick_name = 'anonymous';
			}

			if($obj->is_notice!='Y'||!$this->grant->manager) $obj->is_notice = 'N';

			settype($obj->title, "string");
			if($obj->title == '') $obj->title = cut_str(strip_tags($obj->content),20,'...');
			//If you still Untitled
			if($obj->title == '') $obj->title = 'Untitled';

			// Check that already exist
			$oDocument = $oDocumentModel->getDocument($obj->document_srl, $this->grant->manager);

			// Get linked docs (by alias)
			$wiki_text_parser = $this->getWikiTextParser();
			$linked_documents_aliases = $wiki_text_parser->getLinkedDocuments($obj->content);
					
			// Modified if it already exists
			if($oDocument->isExists() && $oDocument->document_srl == $obj->document_srl) {
				//if($oDocument->get('title')=='Front Page') $obj->title = 'Front Page';
				$output = $oDocumentController->updateDocument($oDocument, $obj);

				// Have been successfully modified the hierarchy/ alias change
				if($output->toBool()) {
					// Update alias
					$oDocumentController->deleteDocumentAliasByDocument($obj->document_srl);
					$aliasName = Context::get('alias');
					if(!$aliasName) $aliasName = $this->beautifyEntryName($obj->title);
					$oDocumentController->insertAlias($obj->module_srl, $obj->document_srl, $aliasName);
					
					// Update linked docs
					if(count($linked_documents_aliases) > 0){
						$oWikiController = getController('wiki');
						$oWikiController->updateLinkedDocuments($obj->document_srl, $linked_documents_aliases, $obj->module_srl);					
					}
				}
				$msg_code = 'success_updated';
				
				// remove document from cache
				$oCacheHandler = &CacheHandler::getInstance('object', null, true);
				if($oCacheHandler->isSupport()){
					$object_key = sprintf('%s.%s.php', $obj->document_srl, Context::getLangType());
					$cache_key = $oCacheHandler->getGroupKey('wikiContent', $object_key);
					$oCacheHandler->delete($cache_key);				
				}				

			
			// if this is a new document
			} else {
				$output = $oDocumentController->insertDocument($obj);
				$msg_code = 'success_registed';
				$obj->document_srl = $output->get('document_srl');
				
				// Insert Alias
				$aliasName = Context::get('alias');
				if(!$aliasName) $aliasName = $this->beautifyEntryName($obj->title);				
				$oDocumentController->insertAlias($obj->module_srl, $obj->document_srl, $aliasName);
				
				// Insert linked docs
				if(count($linked_documents_aliases) > 0){				
					$oWikiController = getController('wiki');
					$oWikiController->insertLinkedDocuments($obj->document_srl, $linked_documents_aliases, $obj->module_srl);									
				}
			}

			// Stop when an error occurs
			if(!$output->toBool()) return $output;

			$this->recompileTree($this->module_srl);
		
			// Returns the results
			$entry = $oDocumentModel->getAlias($output->get('document_srl'));
			// Registration success message
			$this->setMessage($msg_code);
			
			if($entry) {
				$site_module_info = Context::get('site_module_info');
				$url = getSiteUrl($site_module_info->document,'','mid',$this->module_info->mid,'entry',$entry);
			} else {
				$url = getSiteUrl($site_module_info->document,'','document_srl',$output->get('document_srl'));
			}
			$this->setRedirectUrl($url);

		}
		
		function deleteLinkedDocuments($document_srl){
			$args->document_srl = $document_srl;
			$output = executeQuery('wiki.deleteLinkedDocuments', $args);
			return $output;
		}
		
		function insertLinkedDocuments($document_srl, $alias_list, $module_srl){
			$args->document_srl = $document_srl;
			$args->alias_list = implode(',', $alias_list);
			$args->module_srl = $module_srl;
			$output = executeQuery('wiki.insertLinkedDocuments', $args);
			return $output;
		}
		
		function updateLinkedDocuments($document_srl, $alias_list, $module_srl){
			$output = $this->deleteLinkedDocuments($document_srl);
			if($output->toBool())
				$output = $this->insertLinkedDocuments($document_srl, $alias_list, $module_srl);
			return $output;
		}

		/**
		 * @brief Register comments on the wiki if user is not logged
		 */
		function procWikiInsertCommentNotLogged() {
			$this->procWikiInsertComment();
		}
		
		/**
		 * @brief Register comments on the wiki
		 */
		function procWikiInsertComment() {
			// Check permissions
			if(!$this->grant->write_comment) return new Object(-1, 'msg_not_permitted');

			// extract data required
			$obj = Context::gets('document_srl','comment_srl','parent_srl','content','password','nick_name','nick_name','member_srl','email_address','homepage','is_secret','notify_message');
			$obj->module_srl = $this->module_srl;

			// Check for the presence of document object
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($obj->document_srl);
			if(!$oDocument->isExists()) return new Object(-1,'msg_not_permitted');

			// Create object model of document module
			$oCommentModel = &getModel('comment');

			// Create object controller of document module
			$oCommentController = &getController('comment');

			// Check for the presence of comment_srl
			// if comment_srl is n/a then retrieves a value with getNextSequence()
			if(!$obj->comment_srl)
			{
				$obj->comment_srl = getNextSequence();
			}
			else
			{
				$comment = $oCommentModel->getComment($obj->comment_srl, $this->grant->manager);
			}

			// If there is no new comment_srl
			if($comment->comment_srl != $obj->comment_srl)
			{
				// If there is no new parent_srl
				if($obj->parent_srl)
				{
					$parent_comment = $oCommentModel->getComment($obj->parent_srl);
					if(!$parent_comment->comment_srl)
					{
						return new Object(-1, 'msg_invalid_request');
					}
					$output = $oCommentController->insertComment($obj);
				}
				else
				{
					$output = $oCommentController->insertComment($obj);
				}
				
				if ($output->toBool()) 
				{
					//check if comment writer is admin or not
					$oMemberModel = &getModel("member");
					if (isset($obj->member_srl) && !is_null($obj->member_srl))
					{
						$member_info = $oMemberModel->getMemberInfoByMemberSrl($obj->member_srl);
					}
					else
					{
						$member_info->is_admin = 'N';
					}

					// if current module is using Comment Approval System and comment write is not admin user then
					if($oCommentController->isModuleUsingPublishValidation($this->module_info->module_srl) && $member_info->is_admin != 'Y')
					{
						$this->setMessage('comment_to_be_approved');
					}
					else
					{
						$this->setMessage('success_registed');
					}
				}
			// If you have to modify comment_srl
			}
			else
			{
				$obj->parent_srl = $comment->parent_srl;
				$output = $oCommentController->updateComment($obj, $this->grant->manager);
				//$comment_srl = $obj->comment_srl;
			}

			if(!$output->toBool()) return $output;
			
			$this->add('mid', Context::get('mid'));
			$this->add('document_srl', $obj->document_srl);
			$this->add('comment_srl', $obj->comment_srl);
			$this->setRedirectUrl(Context::get('success_return_url'));
		}
		
		/**
		 * @brief Delete article from the wiki 
		 */
		function procWikiDeleteDocument() {
			$oDocumentController = &getController('document');
			$oDocumentModel = &getModel('document');

			// Check permissions
			if(!$this->grant->delete_document) return new Object(-1, 'msg_not_permitted');

			$document_srl = Context::get('document_srl');
			if(!$document_srl) return new Object(-1,'msg_invalid_request');

			$oDocument = $oDocumentModel->getDocument($document_srl);
			if(!$oDocument || !$oDocument->isExists()) return new Object(-1,'msg_invalid_request');
			//if($oDocument->get('title')=='Front Page') return new Object(-1,'msg_invalid_request');

			$output = $oDocumentController->deleteDocument($oDocument->document_srl);
			if(!$output->toBool()) return $output;

			$oDocumentController->deleteDocumentAliasByDocument($oDocument->document_srl);
			$this->recompileTree($this->module_srl);

			$tree_args->module_srl = $this->module_srl;
			$tree_args->document_srl = $oDocument->document_srl;
			$output = executeQuery('wiki.deleteTreeNode', $tree_args);
        
			// remove document from cache
			$oCacheHandler = &CacheHandler::getInstance('object', null, true);
			if($oCacheHandler->isSupport()){
				$object_key = sprintf('%s.%s.php', $document_srl, Context::getLangType());
				$cache_key = $oCacheHandler->getGroupKey('wikiContent', $object_key);
				$oCacheHandler->delete($cache_key);				
			}			

			$site_module_info = Context::get('site_module_info');
			$this->setRedirectUrl(getSiteUrl($site_module_info->domain,'','mid',$this->module_info->mid));
		}

		
		/**
		 * @brief Delete comment from the wiki
		 */
		function procWikiDeleteComment() {
			// check the comment's sequence number 
			$comment_srl = Context::get('comment_srl');
			if(!$comment_srl) return $this->doError('msg_invalid_request');

			// create controller object of comment module 
			$oCommentController = &getController('comment');

			$output = $oCommentController->deleteComment($comment_srl, $this->grant->manager);
			if(!$output->toBool()) return $output;

			$this->add('mid', Context::get('mid'));
			$this->add('page', Context::get('page'));
			$this->add('document_srl', $output->get('document_srl'));
			$this->setRedirectUrl(Context::get('success_return_url'));
			//$this->setMessage('success_deleted');
		}

		
		/**
		 * @brief Change position of the document on hierarchy
		 */
		function procWikiMoveTree() {
			// Check permissions
			if(!$this->grant->write_document) return new Object(-1, 'msg_not_permitted');

			// request arguments
			$args = Context::gets('parent_srl','target_srl','source_srl');

			// retrieve Node information
			$output = executeQuery('wiki.getTreeNode', $args);
			$node = $output->data;
			if(!$node->document_srl) return new Object('msg_invalid_request');

			$args->module_srl = $node->module_srl;
			$args->title = $node->title;
			if(!$args->parent_srl) $args->parent_srl = 0;
			// target without parent list_order must have a minimum list_order
			if(!$args->target_srl)
			{
				$list_order->parent_srl = $args->parent_srl;
				$output = executeQuery('wiki.getTreeMinListorder',$list_order);
				if($output->data->list_order) $args->list_order = $output->data->list_order-1;
				// target이 있으면 그 target of list_order + 1
			}
			else
			{
				$t_args->source_srl = $args->target_srl;
				$output = executeQuery('wiki.getTreeNode', $t_args);
				$target = $output->data;

				// target보다 list_order가 크고 부모가 같은 node에 대해서 list_order+2를 해주고 선택된 node에 list_order+1을 해줌
				$update_args->module_srl = $target->module_srl;
				$update_args->parent_srl = $target->parent_srl;
				$update_args->list_order = $target->list_order;
				if(!$update_args->parent_srl) $update_args->parent_srl = 0;
				$output = executeQuery('wiki.updateTreeListOrder', $update_args);
				if(!$output->toBool()) return $output;

				// target을 원위치 (list_order중복 문제로 인하여 1번 더 업데이트를 시도함) <- why?
				/*$restore_args->module_srl = $target->module_srl;
				$restore_args->source_srl = $target->document_srl;
				$restore_args->list_order = $target->list_order;
				$output = executeQuery('wiki.updateTreeNode', $restore_args);
				if(!$output->toBool()) return $output;*/

				$args->list_order = $target->list_order+1;
			}
			if(!$node->is_exists) $output = executeQuery('wiki.insertTreeNode',$args);
			else $output = executeQuery('wiki.updateTreeNode',$args);
			if(!$output->toBool()) return $output;

			if($args->list_order)
			{
				$doc->document_srl = $args->source_srl;
				$doc->list_order = $args->list_order;
				$output = executeQuery('wiki.updateDocumentListOrder', $doc);
				if(!$output->toBool()) return $output;
			}

			$this->recompileTree($this->module_srl);
		}


		/**
		 * @brief recreate Wiki the hierarchy
		 **/
		function procWikiRecompileTree() {
			if(!$this->grant->write_document) return new Object(-1,'msg_not_permitted');
			return $this->recompileTree($this->module_srl);
		}

		function recompileTree($module_srl) {
			$oWikiModel = &getModel('wiki');
			$list = $oWikiModel->loadWikiTreeList($module_srl);

			$dat_file = sprintf('%sfiles/cache/wiki/%d.dat', _XE_PATH_,$module_srl);
			$xml_file = sprintf('%sfiles/cache/wiki/%d.xml', _XE_PATH_,$module_srl);

			$buff = '';
			$xml_buff = "<root>\n";

			// cache file creation
			foreach($list as $key => $val) {
				$buff .= sprintf('%d,%d,%d,%d,%s%s',$val->parent_srl,$val->document_srl,$val->depth,$val->childs,$val->title,"\n");
				$xml_buff .= sprintf('<node node_srl="%d" parent_srl="%d"><![CDATA[%s]]></node>%s', $val->document_srl, $val->parent_srl, $val->title,"\n");
			}

			$xml_buff .= '</root>';

			FileHandler::writeFile($dat_file, $buff);
			FileHandler::writeFile($xml_file, $xml_buff);

			return new Object();

		}


		/**
		 * @brief Confirm password for modifying non-members Comments
		 */
		function procWikiVerificationPassword()
		{
			$password = Context::get('password');
			$comment_srl = Context::get('comment_srl');

			$oMemberModel = &getModel('member');

			if($comment_srl)
			{
				$oCommentModel = &getModel('comment');
				$oComment = $oCommentModel->getComment($comment_srl);
				if(!$oComment->isExists()) return new Object(-1, 'msg_invalid_request');

				if(!$oMemberModel->isValidPassword($oComment->get('password'), $password)) return new Object(-1, 'msg_invalid_password');
				
				$oComment->setGrant();
			}
		}
		
		/*
		 * @brief function, used by Ajax call, that return curent version and one of history version of the document for making diff
		 */
		function procWikiContentDiff()
		{
		    $document_srl = Context::get("document_srl");
		    $history_srl = Context::get("history_srl");
		    $oDocumentModel = &getModel('document');
		    $oDocument = $oDocumentModel->getDocument($document_srl);
		    $current_content = $oDocument->get('content');
		    $history_content = $oDocumentModel->getHistory($history_srl)->content;
		    $this->add('old', $history_content);
		    $this->add('current', $current_content);
		}
		
		/*
		 * @brief function, used by Ajax call, that return HTML Comment Editor
		 */
		function procDispCommentEditor()
		{
			$document_srl = Context::get("document_srl");
			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);
			$editor = $oDocument->getCommentEditor();
			$oEditorModel = &getModel('editor');

			// get an editor
			$option->primary_key_name = 'comment_srl';
			$option->content_key_name = 'content';
			$option->allow_fileupload = false;
			$option->enable_autosave = false;
			$option->disable_html = true;
			$option->enable_default_component = false;
			$option->enable_component = false;
			$option->resizable = true;
			$option->height = 150;
			$editor = $oEditorModel->getEditor(0, $option);
			Context::set('editor', $editor);
			$this->add('editor',$editor);
		}
		
		/*
		 * @brief Write document to chache
		 */
		private function writeDocToCache($document_srl, $content)
		{
			// generate the file path
			$phpFileFullPath = sprintf(wiki::CACHE_DIR . "/%s.%s.php", $document_srl, Context::getLangType());
			// Remove previous cache
			FileHandler::removeFile($phpFileFullPath);
			// render the content to convert wiki links to HTML links.
			$wikiView = &getView('wiki');
			$wikiView->mid = $this->module_info->mid;
			$wikiView->module_info = $this->module_info;
			$content = $wikiView->_renderWikiContent($document_srl, $content);
			// Write contents to a file.
			FileHandler::writeFile($phpFileFullPath, $content);
		}		

		public function recreateCache()
		{
			$document_srl = Context::get('document_srl');

			if (!$document_srl)
			{
				return new Object(-1, 'msg_invalid_request');
			}

			$oDocumentModel = &getModel('document');
			$oDocument = $oDocumentModel->getDocument($document_srl);

			if (!$oDocument->isExists())
			{
				return new Object(-1, 'msg_invalid_request');
			}

			$oModuleModel = &getModel('module');
			$this->module_info = $oModuleModel->getModuleInfoByModuleSrl($oDocument->get('module_srl'));

			if (!$this->module_info->module_srl || !$this->module_info->mid)
			{
				return new Object(-1, 'msg_invalid_request');
			}

			$this->writeDocToCache($document_srl, $oDocument->get('content'));

			$oDocument->alias_title = $oDocumentModel->getAlias($oDocument->document_srl);
			$this->add('redirect_url', getUrl('', 'mid', $this->module_info->mid, 'entry', $oDocument->alias_title));
			return new Object(0, 'successfully_cached');
		}    
		
	}
?>
