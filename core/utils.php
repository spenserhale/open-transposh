<?php
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

/**
 *
 * Contains utility functions which are shared across the plugin.
 *
 */

require_once("constants.php");
require_once("logging.php");

/*
 * Remove from url any language (or editing) params that were added for our use.
 * Return the scrubed url
 */
function cleanup_url($url, $home_url, $remove_host = false) {
    
    $parsedurl = @parse_url($url);
    //cleanup previous lang & edit parameter from url

    if (isset($parsedurl['query'])) {
        $params = explode('&',$parsedurl['query']);
        foreach ($params as $key => $param) {
            if (stripos($param,LANG_PARAM) === 0) unset ($params[$key]);
            if (stripos($param,EDIT_PARAM) === 0) unset ($params[$key]);
        }
    }
    // clean the query
    unset($parsedurl['query']);
    if(isset($params) && $params) {
        $parsedurl['query'] = implode('&',$params);
    }

    //cleanup lang identifier in permalinks
    //remove the language from the url permalink (if in start of path, and is a defined language)
    $home_path = rtrim(parse_url($home_url,PHP_URL_PATH),"/");
    logger ("home: $home_path ".$parsedurl['path'],5);
    if ($home_path && strpos($parsedurl['path'], $home_path) === 0) {
        logger ("homein!: $home_path",5);
        $parsedurl['path'] = substr($parsedurl['path'],strlen($home_path));
        $gluebackhome = true;
    }
    
    if (strlen($parsedurl['path']) > 2) {
        $secondslashpos = strpos($parsedurl['path'], "/",1);
        if (!$secondslashpos) $secondslashpos = strlen($parsedurl['path']);
        $prevlang =  substr($parsedurl['path'],1,$secondslashpos-1);
        if (isset ($GLOBALS['languages'][$prevlang])) {
            logger ("prevlang: ".$prevlang,4);
            $parsedurl['path'] = substr($parsedurl['path'],$secondslashpos);
        }
    }
    if ($gluebackhome) $parsedurl['path'] = $home_path.$parsedurl['path'];
    if ($remove_host) {
        unset ($parsedurl['scheme']);
        unset ($parsedurl['host']);
    }
    $url = glue_url($parsedurl);
    return $url;
}

/**
 * Update the given url to include language params.
 * @param string $url - Original URL to rewrite
 * @param string $lang - Target language code
 * @param boolean $is_edit - should url indicate editing
 * @param boolean $use_params_only - only use paramaters and avoid permalinks
 */
// Should send a transposh interface to here TODO - enable permalinks rewrite
function rewrite_url_lang_param($url,$home_url, $enable_permalinks_rewrite, $lang, $is_edit, $use_params_only=FALSE) {
    logger("rewrite old url: $url, permalinks: $enable_permalinks_rewrite, lang: $lang, is_edit: $is_edit, home_url: $home_url",5);

    $newurl = str_replace('&#038;', '&', $url);
    $newurl = html_entity_decode($newurl, ENT_NOQUOTES);
    $parsedurl = parse_url($newurl);

    // if we are dealing with some other url, we won't touch it!
    if (isset($parsedurl['host']) && !($parsedurl['host'] == parse_url($home_url,PHP_URL_HOST))) {
        return $url;
    }

    //remove prev lang and edit params - from query string - reserve other params
    if (isset($parsedurl['query'])) {
        $params = explode('&',$parsedurl['query']);
        foreach ($params as $key => $param) {
            if (stripos($param,LANG_PARAM) === 0) unset ($params[$key]);
            if (stripos($param,EDIT_PARAM) === 0) unset ($params[$key]);
        }
    }
    // clean the query
    unset($parsedurl['query']);

    //remove the language from the url permalink (if in start of path, and is a defined language)
    $home_path = rtrim(parse_url($home_url,PHP_URL_PATH),"/");
    logger ("home: $home_path ".$parsedurl['path'],5);
    if ($home_path && strpos($parsedurl['path'], $home_path) === 0) {
        logger ("homein!: $home_path",5);
        $parsedurl['path'] = substr($parsedurl['path'],strlen($home_path));
        $gluebackhome = true;
    }
    if (strlen($parsedurl['path']) > 2) {
        $secondslashpos = strpos($parsedurl['path'], "/",1);
        if (!$secondslashpos) $secondslashpos = strlen($parsedurl['path']);
        $prevlang =  substr($parsedurl['path'],1,$secondslashpos-1);
        if (isset ($GLOBALS['languages'][$prevlang])) {
            logger ("prevlang: ".$prevlang,4);
            $parsedurl['path'] = substr($parsedurl['path'],$secondslashpos);
        }
    }

    //override the use only params - admin configured system to not touch permalinks
    if(!$enable_permalinks_rewrite) {
        $use_params_only = TRUE;
    }

    //$params ="";
    if($is_edit) {
        $params[edit] = EDIT_PARAM . '=1';
    }

    if($use_params_only) {
        $params['lang'] = LANG_PARAM . "=$lang";
    }
    else {
        if (!$parsedurl['path']) $parsedurl['path'] = "/";
        $parsedurl['path'] = "/".$lang.$parsedurl['path'];
    }
        if ($gluebackhome) $parsedurl['path'] = $home_path.$parsedurl['path'];

    //insert params to url
    if(isset($params) && $params) {
        $parsedurl['query'] = implode('&',$params);
        logger("params: $params",4);
    }

    // more cleanups
    //$url = preg_replace("/&$/", "", $url);
    //$url = preg_replace("/\?$/", "", $url);

    //    $url = htmlentities($url, ENT_NOQUOTES);
    $url = glue_url($parsedurl);
    logger("new url: $url",5);
    return $url;
}

function get_language_from_url($url, $home_url) {

    $parsedurl = @parse_url($url);

    //option 1, lanaguage is in the query ?lang=xx
    if (isset($parsedurl['query'])) {
        $params = explode('&',$parsedurl['query']);
        foreach ($params as $key => $param) {
            if (stripos($param,LANG_PARAM) === 0) {
                $langa = explode("=",$params[$key]);
                return ($langa[1]);
            }
        }
    }

    //option 2, language is in permalink

    //cleanup lang identifier in permalinks
    //remove the language from the url permalink (if in start of path, and is a defined language)
    $home_path = rtrim(parse_url($home_url,PHP_URL_PATH),"/");
//    logger ("home: $home_path ".$parsedurl['path'],5);
    if ($home_path && strpos($parsedurl['path'], $home_path) === 0) {
//        logger ("homein!: $home_path",5);
        $parsedurl['path'] = substr($parsedurl['path'],strlen($home_path));
//        $gluebackhome = true;
    }

    if (strlen($parsedurl['path']) > 2) {
        $secondslashpos = strpos($parsedurl['path'], "/",1);
        if (!$secondslashpos) $secondslashpos = strlen($parsedurl['path']);
        $prevlang =  substr($parsedurl['path'],1,$secondslashpos-1);
        if (isset ($GLOBALS['languages'][$prevlang])) {
            //logger ("prevlang: ".$prevlang,4);
            //$parsedurl['path'] = substr($parsedurl['path'],$secondslashpos);
            return $prevlang;
        }
    }
    return false;
}

/**
 * glue a parse_url array back to a url
 * @param array $parsed url_parse style array
 * @return combined url
 */
function glue_url($parsed) {
    if (!is_array($parsed)) {
        return false;
    }

    $uri = isset($parsed['scheme']) ? $parsed['scheme'].':'.((strtolower($parsed['scheme']) == 'mailto') ? '' : '//') : '';
    $uri .= isset($parsed['user']) ? $parsed['user'].(isset($parsed['pass']) ? ':'.$parsed['pass'] : '').'@' : '';
    $uri .= isset($parsed['host']) ? $parsed['host'] : '';
    $uri .= isset($parsed['port']) ? ':'.$parsed['port'] : '';

    if (isset($parsed['path'])) {
        $uri .= (substr($parsed['path'], 0, 1) == '/') ?
            $parsed['path'] : ((!empty($uri) ? '/' : '' ) . $parsed['path']);
    }

    $uri .= isset($parsed['query']) ? '?'.$parsed['query'] : '';
    $uri .= isset($parsed['fragment']) ? '#'.$parsed['fragment'] : '';

    return $uri;
}
/**
 * Encode a string as base 64 while avoiding characters which should be avoided
 * in uri, e.g. + is interpeted as a space.
 */
function base64_url_encode($input) {
    return strtr(base64_encode($input), '+/=', '-_,');
}

/**
 * Decode a string previously decoded with base64_url_encode
 */
function base64_url_decode($input) {
    return base64_decode(strtr($input, '-_,', '+/='));
}

/**
 * Function to display a flag
 * @param string $path path to flag images
 * @param string $flag the flag (normally iso code)
 * @param string $language the name of the lanaguage
 * @param boolean $css using css code?
 * @return string Html with flag
 */
function display_flag ($path, $flag, $language, $css = false) {
    if (!$css) {
        return  "<img src=\"$path/$flag.png\" title=\"$language\" alt=\"$language\"/>";
    } else {
        return "<span title=\"$language\" class=\"trf trf-{$flag}\"></span>";
    }
}

?>