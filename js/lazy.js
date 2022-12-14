/*
 * xLazyLoader 1.5 - Plugin for jQuery
 * 
 * Load js, css and images asynchron and get different callbacks
 *
 * Dual licensed under the MIT (http://www.opensource.org/licenses/mit-license.php)
 * and GPL (http://www.opensource.org/licenses/gpl-license.php) licenses.
 *
 * Depends:
 *   jquery.js
 *
 *  Copyright (c) 2010 Oleg Slobodskoi (jsui.de)
 */

;(function($){

$.xLazyLoader =  function ( method, options ) {
    if ( typeof method == 'object' ) {
        options = method;
        method = 'init';
    };
    new xLazyLoader()[method](options);
};

$.xLazyLoader.defaults = {
    js: [], css: [], img: [],
    jsKey: null, cssKey: null, imgKey: null,
    name: null,
    timeout: 20000,
    //success callback for all files
    success: $.noop, 
    //error callback - by load errors / timeout
    error: $.noop,
    //complete callbck - by success or errors
    complete: $.noop,
    //success callback for each file
    each: $.noop 
};

var head = document.getElementsByTagName("head")[0];

function xLazyLoader ()
{

    var self = this,
        s,
        loaded = [],
        errors = [],
        tTimeout,
        cssTimeout,
        toLoad,
        files = []
    ;
    
    this.init = function ( options )
    {
        if ( !options ) return;
        
        s = $.extend({}, $.xLazyLoader.defaults, options);
        toLoad = {js: s.js, css: s.css, img: s.img};
        
        $.each(toLoad, function( type, f ){
            if ( typeof f == 'string' )        
                f = f.split(',');
            files = files.concat(f);    
        });

        if ( !files.length ) {
            dispatchCallbacks('error');
            return;    
        };

        if (s.timeout) {
            tTimeout = setTimeout(function(){
                var handled = loaded.concat(errors);
                /* search for unhandled files */
                $.each(files, function(i, file){
                    $.inArray(file, handled) == -1 && errors.push(file);        
                });
                dispatchCallbacks('error');            
            }, s.timeout);
        };


        $.each(toLoad, function(type, urls){
            if ( $.isArray(urls) )
                $.each( urls, function(i, url){
                    load(type, url);
                });
            else if (typeof urls == 'string')
                load(type, urls);
        });
        


    };

    this.js = function ( src, callback, name, key )
    {
        var $script = $('script[src*="'+src+'"]');
        if ( $script.length ) {
            $script.attr('pending') ? $script.bind('scriptload',callback) : callback();
            return;
        };
        
        var s = document.createElement('script');
        s.setAttribute("type","text/javascript");
        s.setAttribute('charset', 'UTF-8');
        s.setAttribute("src", src + key);
        s.setAttribute('id', name);
        s.setAttribute('pending', 1);
        // Mozilla only
        s.onerror = addError;
        
        
        $(s).bind('scriptload',function(){
            $(this).removeAttr('pending');
            callback();
             //unbind load event
             //timeout because of pending callbacks
            setTimeout(function(){
                $(s).unbind('scriptload');
            },10);
        });
        
        // jQuery doesn't handles onload event special for script tag,
        var done = false;
        s.onload = s.onreadystatechange = function() {
            if ( !done && ( !this.readyState || /loaded|complete/.test(this.readyState) ) ) {
                done = true;
                // Handle memory leak in IE
                s.onload = s.onreadystatechange = null;
                $(s).trigger('scriptload'); 
            };
        };
        head.appendChild(s);
    
    };

    this.css = function ( href, callback, name, key )
    {

        if ( $('link[href*="'+href+'"]').length ) {
            callback();
            return;
        };
        

        var link = $('<link rel="stylesheet" type="text/css" media="all" href="'+ href + key + '" id="'+name+'"></link>')[0];
       /* if ( $.browser.msie ) {
            link.onreadystatechange = function () {
                /loaded|complete/.test(link.readyState) && callback();
            };
        } else if ( $.browser.opera ) {
            link.onload = callback;
        } else {*/
            /* 
             * Mozilla, Safari, Chrome 
             * unfortunately it is inpossible to check if the stylesheet is really loaded or it is "HTTP/1.0 400 Bad Request"
             * the only way to do this is to check if some special properties  were set, so there is no error callback for stylesheets -
             * it fires alway success
             * 
             * There is also no access to sheet properties by crossdomain stylesheets, 
             * so we fire callback immediately
             */
            
            var hostname = location.hostname.replace('www.',''),
                hrefHostname = /http:/.test(href) ? /^(\w+:)?\/\/([^\/?#]+)/.exec( href )[2] : hostname;
            hostname != hrefHostname && true ?  
                callback()
                :  
                //stylesheet is from the same domain or it is not firefox
                (function(){
                    try {
                        link.sheet.cssRules;
                    } catch (e) {
                        cssTimeout = setTimeout(arguments.callee, 20);
                        return;
                    };
                    callback();
                })();
        //};

                
        head.appendChild(link);
    };
    
    this.img = function ( src, callback, name, key )
    {
        var img = new Image();
        img.onload = callback;
        img.onerror = addError;
        img.src = src + key;
    };
    
    /* It works only for css */
    this.disable = function ( name )
    {    
        $('#lazy-loaded-'+name, head).attr('disabled', 'disabled');
    };

    /* It works only for css */
    this.enable = function ( name )
    {    
        $('#lazy-loaded-'+name, head).removeAttr('disabled');
    };
    
    /*
     * By removing js tag, script ist still living in browser memory,
     * css will be really destroyed
     */
    this.destroy = function ( name )
    {
        $('#lazy-loaded-'+name, head).remove();    
    };
    
    function load ( type, url ) {
        self[type](url, function(status) { 
            status == 'error' ? errors.push(url) : loaded.push(url) && s.each(url);
            checkProgress();
        }, 'lazy-loaded-'+ (s.name ? s.name : new Date().getTime()), s[type+'Key'] ? '?key='+s[type+'Key'] : '' );
    };
    
    function dispatchCallbacks ( status ) {
        s.complete(status, loaded, errors);
        s[status]( status=='error' ? errors : loaded);
        clearTimeout(tTimeout);
        clearTimeout(cssTimeout);
    };
    
    function checkProgress () {
        if (loaded.length == files.length) dispatchCallbacks('success')
        else if (loaded.length+errors.length == files.length) dispatchCallbacks('error');
    };
    
    function addError () {
        errors.push(this.src);    
        checkProgress();
    };

};

})(t_jp.$);