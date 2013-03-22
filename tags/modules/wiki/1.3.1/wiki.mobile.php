<?php

require_once(_XE_PATH_.'modules/wiki/wiki.view.php');

class wikiMobile extends wikiView {
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
	$this->except_notice = $this->module_info->except_notice == 'N' ? false : true;

	/**
	* Check consultation stats.
	* If the current user is not logged the Writing / Comment / List / View grants will be removes
	**/
	if($this->module_info->consultation == 'Y' && !$this->grant->manager) 
	{
	    $this->consultation = true; 
	    if(!Context::get('is_logged'))
	    {
			$this->grant->list = $this->grant->write_document = $this->grant->write_comment = $this->grant->view = false;
	    }
	} 
	else 
	{
	    $this->consultation = false;
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
}

?>
