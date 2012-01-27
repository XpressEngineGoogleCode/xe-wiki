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
	* 상담 기능 체크. 현재 게시판의 관리자이면 상담기능을 off시킴
	* 현재 사용자가 비로그인 사용자라면 글쓰기/댓글쓰기/목록보기/글보기 권한을 제거함
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
