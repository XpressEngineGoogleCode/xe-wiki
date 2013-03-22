function viewHistory(history_srl) {
    var zone = jQuery('#historyContent'+history_srl);
    if(zone.css('display')=='block') zone.css('display','none');
    else zone.css('display','block');
}
/**
 * @file   modules/document/tpl/js/document_category.js
 * @author sol (sol@ngleader.com)
 * @brief  document 모듈의 category tree javascript
 **/

var simpleTreeCollection;
var leftWidth;
var docHeight;
var marginRight = 50;
var titleDivShowHideTree = new Array();

function Tree(){
    var url = request_uri.setQuery('mid',current_mid).setQuery('act','getWikiTreeList');
    if(typeof(xeVid)!='undefined') url = url.setQuery('vid',xeVid);

    // clear tree;
    jQuery('#tree > ul > li > ul').remove();

    //ajax get data and transeform ul il
    jQuery.get(url,function(data){
        jQuery(data).find("node").each(function(i){
            var title = jQuery(this).text();
            var node_srl = jQuery(this).attr("node_srl");
            var parent_srl = jQuery(this).attr("parent_srl");

            var url = request_uri;
            var args = new Array("mid="+current_mid, "entry="+title);
            if(typeof(xeVid)!='undefined') args[args.length] = "vid="+xeVid;
            url = request_uri+'?'+args.join('&');

            // node
            var node = jQuery('<li id="tree_'+node_srl+'" rel="'+url+'"><span>'+title+'</span></li>');

            // insert parent child
            if(parent_srl>0){
                if(jQuery('#tree_'+parent_srl+'>ul').length==0) jQuery('#tree_'+parent_srl).append(jQuery('<ul>'));
                jQuery('#tree_'+parent_srl+'> ul').append(node);
            }else{
                if(jQuery('#tree ul.simpleTree > li > ul').length==0) jQuery("<ul>").appendTo('#tree ul.simpleTree > li');
                jQuery('#tree ul.simpleTree > li > ul').append(node);
            }
        });

        // draw tree
        simpleTreeCollection = jQuery('.simpleTree').simpleTree({
            autoclose: false,
            afterClick:function(node){ 
                location.href = node.attr('rel');
                return false;
            },
            afterMove:function(destination, source, pos){
                if(!isManageGranted) return;
                if(destination.size() == 0){
                    Tree();
                    return;
                }
                var parent_srl = destination.attr('id').replace(/.*_/g,'');
                var source_srl = source.attr('id').replace(/.*_/g,'');

                var target = source.prevAll("li:not([class^=line])");
                var target_srl = 0;
                if(target.length >0){
                    target_srl = source.prevAll("li:not([class^=line])").get(0).id.replace(/.*_/g,'');
                }

                jQuery.exec_json("wiki.procWikiMoveTree",{"mid":current_mid,"parent_srl":parent_srl,"target_srl":target_srl,"source_srl":source_srl}, function(data){Tree();});

            },
            beforeMovedToLine : function() {return true;},
            beforeMovedToFolder : function() {return true;},
            afterAjax:function() { },

            docToFolderConvert:true,
            drag:isManageGranted
        });
        jQuery("[class*=close]", simpleTreeCollection[0]).each(function(){
            simpleTreeCollection[0].nodeToggle(this);
        });
    },"xml");
}

function resizeDiv(docHeight)
{
	
	if (docHeight < (jQuery(window).height()-140))
    {
		docHeight = jQuery(window).height()-140;
    }
    jQuery("#leftSideTreeList").height(docHeight);
	leftWidth = jQuery("#leftSideTreeList").width();
	
	if (jQuery("#columnRight").length > 0)
	{
		jQuery("#wiki").css("min-width",jQuery("#columnRight").width());
		//leftWidth += jQuery("#columnRight").position().left;
	}
	else
	{
		rightWidth = jQuery(document).width()-leftWidth-marginRight;
	}
	leftWidth += jQuery("#wiki").position().left;
	rightWidth = jQuery("#wiki").width()-jQuery("#leftSideTreeList").width()-marginRight;
    // jQuery("#wikiDocument").width(rightWidth);
	 
	if( jQuery("#leftSideTreeList").width() > 1 )
	{
		jQuery("#showHideTree").css("left",(leftWidth-13)+"px");
	}
}

jQuery.fn.decHTML = function() {
  return this.each(function(){
    var me   = jQuery(this);
    var html = me.html();
    me.html("<div style='text-align:left'>"+html.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>')+"</div>");
  });
};

function getDiff(elem,document_srl,history_srl)
{
    var type = "td";
    var diffDiv = jQuery("#"+elem);
    if (!diffDiv.hasClass("hide"))
    {
	diffDiv.addClass("hide");
    }
    else
    {
		jQuery.exec_json
		(
			"wiki.procWikiContentDiff",
			{
				"document_srl": document_srl,
				"history_srl": history_srl
			},
			function(data)
			{
				var old = data.old;
				var current = data.current;
				wDiffHtmlInsertStart = "<span class='wDiffHtmlInsert'>";
				wDiffHtmlInsertEnd = "</span>";
				wDiffHtmlDeleteStart = "<span class='wDiffHtmlDelete'>";
				wDiffHtmlDeleteEnd = "</span>";
				var htmlText = WDiffString(old, current);
				//var htmlText = diffString(old,current);
				jQuery('#diff'+history_srl).html(htmlText).decHTML();
				jQuery(type+'[name*="diff"]').each(function()
				{
					if (!jQuery(this).hasClass("hide")) 
					jQuery(this).addClass("hide");
				});
				jQuery('#diff'+history_srl).toggleClass("hide");
				docHeight = jQuery("#wikiBody").height();
				resizeDiv(docHeight);
			}
		);
    }
    docHeight = jQuery("#wikiBody").height();
    resizeDiv(docHeight);
}

jQuery(document).ready(function(){
    jQuery("#navigation").treeview({
	    animated: "fast",
	    collapsed: false,
	    unique: false,
	    persist: "cookie"
    });
	if (jQuery("#columnRight").length > 0)
	{
		jQuery("#wiki").css("min-width",jQuery("#columnRight").width());
	}
	leftWidth = jQuery("#leftSideTreeList").width();
	slideWidth = jQuery("#leftSideTreeList").width()
	
    jQuery("#showHideTree").click(function()
    {
		var leftTree = jQuery("#leftSideTreeList");
		var toggleButton = jQuery("#showHideTree");
		
		leftTree.animate({
			width: 'toggle'
			}, 500, function() {
			resizeDiv(docHeight);
		});
		
		var wikiBody = jQuery("#wikiBody");
		
		if( leftTree.width() == 1 )
		{
			jQuery("#leftSideTreeList *").hide();
			wikiBody.animate({
				'padding-left': '250px'
			}, 500);				
			toggleButton.animate({
				left: '+='+(slideWidth-13)
			}, 500, function() {
					jQuery("#leftSideTreeList *").show();		
					toggleButton.css('background-position', "0px 0px");
					toggleButton.attr("title",titleDivShowHideTree[0]);
				});
				
		}
		else
		{
			jQuery("#leftSideTreeList *").hide();
			wikiBody.animate({
				'padding-left': '0'
			}, 500);			
			toggleButton.animate({
				left: '-='+(slideWidth-13)
				}, 500, function() {
					jQuery("#leftSideTreeList *").show();
				//jQuery("#wikiDocument").width(jQuery("#wiki").width()-marginRight);
				
				toggleButton.css('background-position', "-13px 0px");
				toggleButton.attr("title",titleDivShowHideTree[1]);
			});
		}
    });
	
	if (jQuery("input[name=title]").length && jQuery("input[name=title]").hasClass("inputTypeText"))
	{
		jQuery("input[name=title]").focus();
	}
	jQuery("div.sitemap").addClass("hide");
});

jQuery(window).load(function() {
    docHeight = jQuery("#wikiBody").height();
    resizeDiv(docHeight);
    if (jQuery("#showHideTree").length > 0)
    {
		titleDivShowHideTree = jQuery("#showHideTree").attr("title").split("/");
		jQuery("#showHideTree").attr("title",titleDivShowHideTree[0]);
    }
});

jQuery(function() {
	/*
    jQuery("#leftSideTreeList").resizable({
	maxHeight: docHeight,
	maxWidth: 350,
	minHeight: docHeight,
	minWidth: 200,
	resize: function() {
	    resizeDiv(docHeight);
	}
    });
	*/
    
});
jQuery(window).resize(function(){
    docHeight = jQuery("#wikiBody").height();
    resizeDiv(docHeight);
});

function loadCommentForm(document_srl)
{
	jQuery.exec_json
	(
		"wiki.procDispCommentEditor",
		{
			"document_srl": document_srl
		},
		function(data)
		{
			var editor = data.editor;
			var pos = -1;
			var posEnd;
			while ((pos = editor.indexOf('<!--#Meta:', pos + 1)) > -1)
			{
				posEnd = editor.indexOf('-->', pos);

				// Check if the resource has extension .CSS
				if (editor.substr(posEnd - 4, 4) == '.css')
				{
					// 10 is the length of "<!--#Meta:"
					jQuery("head").append('<link rel="stylesheet" type="text/css" href="' + editor.substring(pos + 10, posEnd) + '" />');
				}
				else{
					// 10 is the length of "<!--#Meta:"
					jQuery("head").append('<script type="text/javascript" src="' + editor.substring(pos + 10, posEnd) + '"></script>');
				}
			}
			jQuery('div.editor').append(editor);
			jQuery("#editor-box").hide();
			
			jQuery(".wikiEditor .wikiEditorSubmit").show();
			jQuery(".wikiEditor .editorOption").show();
			
			jQuery("div.commentEditor").find(".wikiNavigation").removeClass("hide");
			scrollTo("div.editor");
			docHeight = jQuery("#wikiBody").height();
			resizeDiv(docHeight);
		}
	)
}

function hideEditor()
{
	jQuery(".wikiEditor .wikiEditorSubmit").hide();	
	jQuery(".wikiEditor .editorOption").hide();	
	
	jQuery('div.editor').html("");
	jQuery("div.commentEditor").find(".wikiNavigation").addClass("hide");
	jQuery("#editor-box").show();
	docHeight = jQuery("#wikiBody").height();
	resizeDiv(docHeight);
}

function scrollTo(elem)
{
    jQuery("html, body").animate({scrollTop: jQuery(elem).offset().top}, 2000);
}