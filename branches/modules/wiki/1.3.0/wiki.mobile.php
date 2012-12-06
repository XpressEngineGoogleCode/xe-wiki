<?php
/**
 * File containing the Wiki mobile class
 */

/** Load WikiView class since most of the behaviour is shared */
require_once(_XE_PATH_.'modules/wiki/wiki.view.php');

/**
 * Wiki mobile class
 *
 * @author NHN (developers@xpressengine.com)
 * @package wiki
 */
class WikiMobile extends WikiView {

	/**
	 * Initialize mobile views
	 */
	function init()
    {
		if($this->module_info->list_count)
		{
			$this->list_count = $this->module_info->list_count;
		}
		if($this->module_info->search_list_count)
		{
			$this->search_list_count = $this->module_info->search_list_count;
		}
		if($this->module_info->page_count)
		{
			$this->page_count = $this->module_info->page_count;
		}
		$this->except_notice = $this->module_info->except_notice == 'N' ? FALSE : TRUE;

		/**
		* Check consultation stats.
		* If the current user is not logged the Writing / Comment / List / View grants will be removes
		**/
		if($this->module_info->consultation == 'Y' && !$this->grant->manager)
		{
			$this->consultation = TRUE;
			if(!Context::get('is_logged'))
			{
				$this->grant->list = $this->grant->write_document = $this->grant->write_comment = $this->grant->view = FALSE;
			}
		}
		else
		{
			$this->consultation = FALSE;
		}

		$oDocumentModel = &getModel('document');
		$extra_keys = $oDocumentModel->getExtraKeys($this->module_info->module_srl);
		Context::set('extra_keys', $extra_keys);

		$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		if(!is_dir($template_path)||!$this->module_info->mskin)
		{
			$this->module_info->mskin = 'default';
			$template_path = sprintf("%sm.skins/%s/",$this->module_path, $this->module_info->mskin);
		}
		$this->setTemplatePath($template_path);
    }

	/**
	 * Displays a mobile ready view of a document's comments
	 */
	function displayWikiCommentList(){
		$document_srl = Context::get('document_srl');
		$oDocumentModel = &getModel('document');

		$oDocument  = $oDocumentModel->getDocument($document_srl);

		$_comment_list = $oDocument->getComments();
		Context::set('oDocument', $oDocument);
		Context::set('_comment_list', $_comment_list);

		$this->setTemplateFile('comment');
	}

	/**
	 * Displays a mobile ready view for adding a new comment
	 */
	function dispWikiAddComment() {
		$document_srl = Context::get('document_srl');
		$oDocumentModel = &getModel('document');

		$oDocument  = $oDocumentModel->getDocument($document_srl);
		Context::set('oDocument', $oDocument);
		$this->setTemplateFile('comment_form');
	}
}

?>
