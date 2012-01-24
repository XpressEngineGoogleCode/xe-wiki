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
    rightWidth = jQuery(document).width()-jQuery("#leftSideTreeList").width()-70;
    jQuery("#content_Body").width(rightWidth);
    leftWidth = jQuery("#leftSideTreeList").width();
    //jQuery("#wdth").text(jQuery(window).height()+ " - " + jQuery("#content_Body").height() + " : " + docHeight);
    if ( jQuery("#showHideTree").css("left") != "1px")
    jQuery("#showHideTree").css("left",jQuery("#leftSideTreeList").width());
}

jQuery.fn.decHTML = function() {
  return this.each(function(){
    var me   = jQuery(this);
    var html = me.html();
    me.html(html.replace(/&amp;/g,'&').replace(/&lt;/g,'<').replace(/&gt;/g,'>'));
  });
};

function getDiff(elem,document_srl,history_srl)
{
    var type = "div";
    var diffDiv = elem.find(type).eq(1);
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
		docHeight = jQuery("#content_Body").height();
		resizeDiv(docHeight);
	    }
	);
    }
    docHeight = jQuery("#content_Body").height();
    resizeDiv(docHeight);
}

jQuery(document).ready(function(){
    jQuery("#navigation").treeview({
	    animated: "fast",
	    collapsed: false,
	    unique: false,
	    persist: "cookie"
    });
    leftWidth = jQuery("#leftSideTreeList").width();
    jQuery("#showHideTree").click(function()
    {
	jQuery("#leftSideTreeList").animate({
	    width: 'toggle'
	}, 200, function() {
	    resizeDiv(docHeight);
	});
	if( jQuery("#showHideTree").css("left") == "1px" )
	{
	    jQuery("#showHideTree").animate({
		left: '+='+(leftWidth-1)
	    }, 200, function() {
		jQuery("#showHideTree").css('background-position', "0px 0px");
		jQuery("#showHideTree").attr("title",titleDivShowHideTree[0]);
	    });
	}
	else
	{
	    jQuery("#showHideTree").animate({
		left: '-='+(leftWidth-1)
	    }, 200, function() {
		jQuery("#showHideTree").css('background-position', "-13px 0px");
		jQuery("#showHideTree").attr("title",titleDivShowHideTree[1]);
	    });
	}
    });
});
jQuery(window).load(function() {
    docHeight = jQuery("#content_Body").height();
    resizeDiv(docHeight);
    if (jQuery("#showHideTree").length > 0)
    {
	titleDivShowHideTree = jQuery("#showHideTree").attr("title").split("/");
	jQuery("#showHideTree").attr("title",titleDivShowHideTree[0]);
    }
});

jQuery(function() {
    jQuery("#leftSideTreeList").resizable({
	maxHeight: docHeight,
	maxWidth: 350,
	minHeight: docHeight,
	minWidth: 200,
	resize: function() {
	    resizeDiv(docHeight);
	}
    });
    
});
jQuery(window).resize(function(){
    docHeight = jQuery("#content_Body").height();
    resizeDiv(docHeight);
});
