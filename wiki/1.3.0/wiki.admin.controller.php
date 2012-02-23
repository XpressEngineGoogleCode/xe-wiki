<?php
	/**
	 * @class  wikiAdminController
	 * @author NHN (developers@xpressengine.com)
	 * @brief  wiki 모듈의 admin controller class
	 **/

	class wikiAdminController extends wiki {

		/**
		 * @brief Initialization
		 **/
		function init() {
		}

		/**
		 * @brief Add a wiki module
		 */
		function procWikiAdminInsertWiki($args = null) {
			// module 모듈의 model/controller 객체 생성
			$oModuleController = &getController('module');
			$oModuleModel = &getModel('module');

			$args = Context::getRequestVars();
			$args->module = 'wiki';
			if($args->use_comment!='N') $args->use_comment = 'Y';

			if($args->module_srl) {
				$module_info = $oModuleModel->getModuleInfoByModuleSrl($args->module_srl);
				if($module_info->module_srl != $args->module_srl) unset($args->module_srl);
			}

			if(!$args->module_srl) {
				$output = $oModuleController->insertModule($args);
				$msg_code = 'success_registed';
			} else {
				$output = $oModuleController->updateModule($args);
				$msg_code = 'success_updated';
			}

			if(!$output->toBool()) return $output;

			$this->add('page',Context::get('page'));
			$this->add('module_srl',$output->get('module_srl'));
			$this->setMessage($msg_code);
			

			$returnUrl = Context::get('success_return_url');
			$this->setRedirectUrl($returnUrl);
		}

		/**
		 * @brief Deleting a wiki module
		 */
		function procWikiAdminDeleteWiki() {
			$module_srl = Context::get('module_srl');

			$oModuleController = &getController('module');
			$output = $oModuleController->deleteModule($module_srl);
			if(!$output->toBool()) return $output;

			$this->add('module','wiki');
			$this->add('page',Context::get('page'));
			$this->setMessage('success_deleted');
		}

		/**
		 * @brief Theorem can not be accessed by writing the title
		 */ 
		function procWikiAdminArrangeList() {
			$oModuleModel = &getModel('module');
			$oDocumentController = &getController('document');

			// Verification target Wiki
			$module_srl = Context::get('module_srl');
			$module_info = $oModuleModel->getModuleInfoByModuleSrl($module_srl);
			if(!$module_info->module_srl || $module_info->module != 'wiki') return new Object(-1,'msg_invalid_request');

			// Wiki article of the target entry has no value extraction
			$args->module_srl = $module_srl;
			$output = executeQueryArray('wiki.getDocumentWithoutAlias', $args);
			if(!$output->toBool() || !$output->data) return new Object();

			foreach($output->data as $key => $val) {
				if($val->alias_srl) continue;
				$val->alias_title = wiki::beautifyEntryName($val->alias_title);
				$result = $oDocumentController->insertAlias($module_srl, $val->document_srl, $val->alias_title);
				if(!$result->toBool()) $oDocumentController->insertAlias($module_srl, $val->document_srl, $val->alias_title.'_'.$val->document_srl);
			}
		}

	}
?>
