/*  Copyright © 2009 Transposh Team (website : http://transposh.org)
 *
 *	This program is free software; you can redistribute it and/or modify
 *	it under the terms of the GNU General Public License as published by
 *	the Free Software Foundation; either version 2 of the License, or
 *	(at your option) any later version.
 *
 *	This program is distributed in the hope that it will be useful,
 *	but WITHOUT ANY WARRANTY; without even the implied warranty of
 *	MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 *	GNU General Public License for more details.
 *
 *	You should have received a copy of the GNU General Public License
 *	along with this program; if not, write to the Free Software
 *	Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA
 */

// fetch translation from google translate...
function getgt()
{
	jQuery(":button:contains('Suggest - Google')").attr("disabled","disabled").addClass("ui-state-disabled");
	google.language.translate(jQuery("#"+transposh_params['prefix']+"original").val(), "", transposh_params['lang'], function(result) {
		if (!result.error) {
			jQuery("#"+transposh_params['prefix']+"translation").val(jQuery("<div>"+result.translation+"</div>").text())
			.keyup();
		} 
	});
}

//Ajax translation
function ajax_translate(original,translation,source,segment_id) {
    jQuery.ajax({  
        type: "POST",
        url: transposh_params['post_url'],
        data: {token: jQuery("#"+transposh_params['prefix'] + segment_id).attr('token'),
				translation: translation,
				lang: transposh_params['lang'],
				source: source,
				translation_posted: "1"},
        success: function(req) {
        	var pre_translated = jQuery("#"+transposh_params['prefix'] + segment_id).html();
        	var new_text = translation;
        	//reset to the original content - the unescaped version if translation is empty
            if(jQuery.trim(translation).length == 0) {
            	new_text = original;
            }
            // rewrite text for all matching items at once
        	jQuery("."+transposh_params['prefix']+"t,."+transposh_params['prefix']+"u").filter(function() {return jQuery(this).html() == pre_translated;}).html(new_text)
        		.each(function (i) { // handle the image changes
        			var img_segment_id = jQuery(this).attr('id').substr(jQuery(this).attr('id').lastIndexOf('_')+1);
        			jQuery("#"+transposh_params['prefix']+"img_" + img_segment_id).removeClass('tr-icon-yellow').removeClass('tr-icon-green');
                	if(jQuery.trim(translation).length != 0) {
                   		if (source == 1) {
                   			//switch to the auto img
                   			jQuery("#"+transposh_params['prefix']+"img_" + img_segment_id).addClass('tr-icon-yellow');                		
                   		} else {
                    		//	switch to the fix img
                   			jQuery("#"+transposh_params['prefix']+"img_" + img_segment_id).addClass('tr-icon-green');                		
                   		}
                   	}
        		});
                
            //close dialog
        	if (typeof cClick == 'function' && source == 0) {
        		cClick();
        	}
    	},
                
        error: function(req) {
    		if (source == 0) {
    			alert("Error !!! failed to translate.\n\nServer's message: " + req.statusText);
    		}
    	}
    });
}

//function for auto translation
function do_auto_translate() {
	jQuery("."+transposh_params['prefix']+"u").each(function (i) {
		var translated_id = jQuery(this).attr('id');
		google.language.translate(jQuery(this).text(), "", transposh_params['lang'], function(result) {
			if (!result.error) {
				var segment_id = translated_id.substr(translated_id.lastIndexOf('_')+1);
		        ajax_translate(jQuery("#"+translated_id).text(),jQuery("<div>"+result.translation+"</div>").text(),1,segment_id);
		        jQuery("#"+translated_id).addClass(transposh_params['prefix']+"t").removeClass(transposh_params['prefix']+"u");
			} 
		});
	});
}

function confirm_close() {
	jQuery('<div id="dial" title="Close without saving?"><p><span class="ui-icon ui-icon-alert" style="float:left; margin:0 7px 20px 0;"></span>You have made a change to the translation. Are you sure you want to discard it?</p></div>').appendTo("body").dialog({
			bgiframe: true,
			resizable: false,
			height:140,
			modal: true,
			overlay: {
				backgroundColor: '#000',
				opacity: 0.5
			},
			buttons: {
				'Discard': function() {
					jQuery("#"+transposh_params['prefix']+"translation").data("edit", { changed: false});
					jQuery(this).dialog('close');
					jQuery("#tabs").dialog('close');
				},
				Cancel: function() {
					jQuery(this).dialog('close');
				}
			}
		});
}

//Open translation dialog 
function translate_dialog(segment_id) {
	jQuery("#trd-tabs").remove();
	jQuery('<div id="trd-tabs" title="Edit Translation"/>').appendTo("body");
	jQuery("#trd-tabs").append('<ul/>').tabs({ cache: true })
		.tabs('add','#trd-tabs-1','Translate')
		.tabs('add',transposh_params['post_url']+'?tr_token_hist='+jQuery("#"+transposh_params['prefix'] + segment_id).attr('token')+'&lang='+transposh_params['lang'],'History')
		.css("text-align","left")
		.css("padding",0)
		.bind('tabsload', function(event, ui) {
			//TODO, formatting here, not server side
			jQuery("table",ui.panel).addClass("ui-widget ui-widget-content").css({'width' : '95%', 'padding' : '0'});
			jQuery("table thead th:last",ui.panel).after("<th/>");
			jQuery("table thead tr",ui.panel).addClass("ui-widget-header");
			jQuery("table tbody tr",ui.panel).append('<td/>');
			jQuery("table tbody tr:first td:last",ui.panel).append('<span id="'+transposh_params['prefix']+'revert" style="float: left; margin-right: 0.3em;" class="ui-icon ui-icon-scissors"/>');
			jQuery("#"+transposh_params['prefix']+"revert").click(function () { 
		      alert ('hi'); 
			});
		})
		.bind('tabsselect', function(event, ui) {
			// Change buttons
			if (jQuery(ui.tab).text() == 'Translate') {
				jQuery("#trd-tabs").dialog('option', 'buttons', tButtons);
			} else {
				jQuery("#trd-tabs").dialog('option', 'buttons', hButtons);
			}
		})
		.bind('dialogbeforeclose', function(event, ui) {
			if(jQuery("#"+transposh_params['prefix']+"translation").data("edit").changed) {
				confirm_close();
				return false;
			}
		});
	// fix for templates messing with li
	jQuery("#trd-tabs li").css("list-style-type","none").css("list-style-position","outside");
	jQuery("#trd-tabs-1").append(
			'<form id="'+transposh_params['prefix']+'form">' +	
			'<fieldset>' +
			'<label for="original">Original Text</label>' +
			'<textarea cols="80" row="3" name="original" id="'+transposh_params['prefix']+'original" class="text ui-widget-content ui-corner-all" readonly="y"/>' +
			'<label for="translation">Translate To</label>' +
			'<textarea cols="80" row="3" name="translation" id="'+transposh_params['prefix']+'translation" value="" class="text ui-widget-content ui-corner-all"/>' +
			'</fieldset>' +
			'</form>');
	jQuery("#trd-tabs-1 label").css("display","block");
	jQuery("#trd-tabs-1 textarea.text").css({'margin-bottom':'12px', 'width' : '95%', 'padding' : '.4em'});
	jQuery("#"+transposh_params['prefix']+"original").val(jQuery("#"+transposh_params['prefix'] + segment_id).attr('orig'));
	jQuery("#"+transposh_params['prefix']+"translation").val(jQuery("#"+transposh_params['prefix'] + segment_id).html());
	jQuery("#"+transposh_params['prefix']+"translation").data("edit", { changed: false});
	jQuery("#"+transposh_params['prefix']+"translation").keyup(function(e){
		if (jQuery("#"+transposh_params['prefix'] + segment_id).text() != jQuery(this).val()) {
			jQuery(this).css("background","yellow");
			jQuery(this).data("edit", { changed: true});
		} else {
			jQuery(this).css("background","");
			jQuery(this).data("edit", { changed: false});			
		}
    });
	var tButtons =	{
			'Suggest - Google': function() {
				getgt();
			},
			Ok: function() {
				var translation = jQuery('#'+transposh_params['prefix']+'translation').val();
				if(jQuery('#'+transposh_params['prefix']+'translation').data("edit").changed) {
					ajax_translate(jQuery("#"+transposh_params['prefix'] + segment_id).attr('orig'),translation,0,segment_id);
					jQuery("#"+transposh_params['prefix']+"translation").data("edit", { changed: false});
				}
				jQuery(this).dialog('close');
			}
		}; 
	var hButtons =	{
			Close: function() {
				jQuery(this).dialog('close');
			}
		}; 
	jQuery("#trd-tabs").tabs().dialog({
		bgiframe: true,
		modal: true,
		//width: 'auto',
		width: 500,
		buttons: tButtons		
	});
}

//to run at start
jQuery.noConflict();
//read parameters
var transposh_params = new Array(); 
jQuery("script[src*='transposh.js']").each(function (i) {
	var query_string = unescape(this.src.substring(this.src.indexOf('?')+1));
	var parms = query_string.split('&');
	for (var i=0; i<parms.length; i++) {
		var pos = parms[i].indexOf('=');
		if (pos > 0) {
			var key = parms[i].substring(0,pos);
			var val = parms[i].substring(pos+1);
			transposh_params[key] = val;	
		}
	}
});

google.load("language", "1");
jQuery(document).ready(
	function() {
		do_auto_translate();
		if (transposh_params['edit']) {
			// lets add the images
			jQuery("."+transposh_params['prefix']+"t,."+transposh_params['prefix']+"u").each(function (i) {
				var translated_id = jQuery(this).attr('id').substr(jQuery(this).attr('id').lastIndexOf('_')+1);
				jQuery(this).after('<img id="'+transposh_params['prefix']+'img_'+translated_id+'" class="tr-icon" size="12x12" title="'+jQuery(this).attr('orig')+'" src="'+transposh_params['post_url']+'?tp_gif=y"/>');
				jQuery('#'+transposh_params['prefix']+'img_'+translated_id).click(function () {
				      translate_dialog(translated_id);
				      return false;
				      });
				if (jQuery(this).hasClass(transposh_params['prefix']+'t')) {
				if (jQuery(this).attr('source') == '1')
					jQuery('#'+transposh_params['prefix']+'img_'+translated_id).addClass('tr-icon-yellow');
				else
					jQuery('#'+transposh_params['prefix']+'img_'+translated_id).addClass('tr-icon-green');
				}
			});
		}
	}
);

