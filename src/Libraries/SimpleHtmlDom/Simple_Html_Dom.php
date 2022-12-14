<?php

namespace OpenTransposh\Libraries\SimpleHtmlDom;

class Simple_Html_Dom {

	public $root = null;
	public $nodes = array();
	public $callback = null;
	public $lowercase = false;
	protected $pos;
	protected $doc;
	protected $char;
	protected $size;
	protected $cursor;
	protected $parent;
	public $noise = array();
	protected $token_blank = " \t\r\n";
	protected $token_equal = ' =/>';
	protected $token_slash = " />\r\n\t";
	protected $token_attr = ' >';
	// use isset instead of in_array, performance boost about 30%...
	protected $self_closing_tags = array(
		'img'    => 1,
		'br'     => 1,
		'input'  => 1,
		'meta'   => 1,
		'hr'     => 1,
		'base'   => 1,
		'embed'  => 1,
		'spacer' => 1
	);
	protected $block_tags = array( 'root' => 1, 'body' => 1, 'form' => 1, 'div' => 1, 'span' => 1, 'table' => 1 );
	protected $optional_closing_tags = array(
		'tr'   => array( 'tr' => 1, 'td' => 1, 'th' => 1 ),
		'th'   => array( 'th' => 1 ),
		'td'   => array( 'td' => 1 ),
		'li'   => array( 'li' => 1 ),
		'dt'   => array( 'dt' => 1, 'dd' => 1 ),
		'dd'   => array( 'dd' => 1, 'dt' => 1 ),
		'dl'   => array( 'dd' => 1, 'dt' => 1 ),
		'p'    => array( 'p' => 1 ),
		'nobr' => array( 'nobr' => 1 ),
		'link' => array( 'link' => 1 ),
	);

	public function __construct( $str = null ) {
		if ( $str ) {
			if ( preg_match( "/^http:\/\//i", $str ) || is_file( $str ) ) {
				$this->load_file( $str );
			} else {
				$this->load( $str );
			}
		}
	}

	public function __destruct() {
		$this->clear();
	}

	// load html from string
	public function load( $str, $lowercase = true ) {
		// prepare
		$this->prepare( $str, $lowercase );
		// strip out comments
//        logger ("hi");
		/*        $this->remove_noise("'<?xml(.*?)?>'is", true); */
		$this->remove_noise( "'<!--(.*?)-->'is" );
		// strip out cdata
		$this->remove_noise( "'<!\[CDATA\[(.*?)\]\]>'is", true );
		// strip out <script> tags
		$this->remove_noise( "'<\s*script[^>]*[^/]>(.*?)<\s*/\s*script\s*>'is" );
		$this->remove_noise( "'<\s*script\s*>(.*?)<\s*/\s*script\s*>'is" );
		// strip out <style> tags
		$this->remove_noise( "'<\s*style[^>]*[^/]>(.*?)<\s*/\s*style\s*>'is" );
		$this->remove_noise( "'<\s*style\s*>(.*?)<\s*/\s*style\s*>'is" );
		// strip out preformatted tags
		$this->remove_noise( "'<\s*(?:code)[^>]*>(.*?)<\s*/\s*(?:code)\s*>'is" );
		// strip out server side scripts
		$this->remove_noise( "'(<\?)(.*?)(\?>)'s", true );
		// strip smarty scripts
		$this->remove_noise( "'(\{\w)(.*?)(\})'s", true );

		// parsing
		while ( $this->parse() ) {
		}
		// end
		$this->root->_[ Constants::HDOM_INFO_END ] = $this->cursor;
	}

	// load html from file
	public function load_file() {
		$args = func_get_args();
		$this->load( file_get_contents( ...$args ), true );
	}

	// set callback function
	public function set_callback( $function_name ) {
		$this->callback = $function_name;
	}

	// remove callback function
	public function remove_callback() {
		$this->callback = null;
	}

	// save dom as string
	public function save( $filepath = '' ) {
		$ret = $this->root->innertext();
		if ( $filepath !== '' ) {
			file_put_contents( $filepath, $ret );
		}

		return $ret;
	}

	// find dom node by css selector
	public function find( $selector, $idx = null ) {
		return $this->root->find( $selector, $idx );
	}

	// clean up memory due to php5 circular references memory leak...
	public function clear() {
		foreach ( $this->nodes as $n ) {
			$n->clear();
			$n = null;
		}
		// This add next line is documented in the sourceforge repository. 2977248 as a fix for ongoing memory leaks that occur even with the use of clear.
		if ( isset( $this->children ) ) {
			foreach ( $this->children as $n ) {
				$n->clear();
				$n = null;
			}
		}
		if ( isset( $this->parent ) ) {
			$this->parent->clear();
			unset( $this->parent );
		}
		if ( isset( $this->root ) ) {
			$this->root->clear();
			unset( $this->root );
		}
		unset( $this->doc, $this->noise );
	}

	public function dump( $show_attr = true ) {
		$this->root->dump( $show_attr );
	}

	// prepare HTML data and init everything
	protected function prepare( $str, $lowercase = true ) {
		$this->clear();
		$this->doc                                   = $str;
		$this->pos                                   = 0;
		$this->cursor                                = 1;
		$this->noise                                 = array();
		$this->nodes                                 = array();
		$this->lowercase                             = $lowercase;
		$this->root                                  = new Node( $this );
		$this->root->tag                             = 'root';
		$this->root->_[ Constants::HDOM_INFO_BEGIN ] = - 1;
		$this->root->nodetype                        = Constants::HDOM_TYPE_ROOT;
		$this->parent                                = $this->root;
		// set the length of content
		$this->size = strlen( $str );
		if ( $this->size > 0 ) {
			$this->char = $this->doc[0];
		}
	}

	// parse html content
	protected function parse() {
		if ( ( $s = $this->copy_until_char( '<' ) ) === '' ) {
			return $this->read_tag();
		}

		// text
		$node = new Node( $this );
		++ $this->cursor;
		$node->_[ Constants::HDOM_INFO_TEXT ] = $s;
		$this->link_nodes( $node, false );

		return true;
	}

	// read tag info
	protected function read_tag() {
		if ( $this->char !== '<' ) {
			$this->root->_[ Constants::HDOM_INFO_END ] = $this->cursor;

			return false;
		}
		$begin_tag_pos = $this->pos;
		$this->char    = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
		// end tag
		if ( $this->char === '/' ) {
			$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
			$this->skip( $this->token_blank );
			$tag = $this->copy_until_char( '>' );

			// skip attributes in end tag
			if ( ( $pos = strpos( $tag, ' ' ) ) !== false ) {
				$tag = substr( $tag, 0, $pos );
			}

			$parent_lower = strtolower( $this->parent->tag );
			$tag_lower    = strtolower( $tag );

			if ( $parent_lower !== $tag_lower ) {
				if ( isset( $this->optional_closing_tags[ $parent_lower ] ) && isset( $this->block_tags[ $tag_lower ] ) ) {
					$this->parent->_[ Constants::HDOM_INFO_END ] = 0;
					$org_parent                                  = $this->parent;

					while ( ( $this->parent->parent ) && strtolower( $this->parent->tag ) !== $tag_lower ) {
						$this->parent = $this->parent->parent;
					}

					if ( strtolower( $this->parent->tag ) !== $tag_lower ) {
						$this->parent = $org_parent; // restore origonal parent
						if ( $this->parent->parent ) {
							$this->parent = $this->parent->parent;
						}
						$this->parent->_[ Constants::HDOM_INFO_END ] = $this->cursor;

						return $this->as_text_node( $tag );
					}
				} else if ( ( $this->parent->parent ) && isset( $this->block_tags[ $tag_lower ] ) ) {
					$this->parent->_[ Constants::HDOM_INFO_END ] = 0;
					$org_parent                                  = $this->parent;

					while ( ( $this->parent->parent ) && strtolower( $this->parent->tag ) !== $tag_lower ) {
						$this->parent = $this->parent->parent;
					}

					if ( strtolower( $this->parent->tag ) !== $tag_lower ) {
						$this->parent                                = $org_parent; // restore origonal parent
						$this->parent->_[ Constants::HDOM_INFO_END ] = $this->cursor;

						return $this->as_text_node( $tag );
					}
				} else if ( ( $this->parent->parent ) && strtolower( $this->parent->parent->tag ) === $tag_lower ) {
					$this->parent->_[ Constants::HDOM_INFO_END ] = 0;
					$this->parent                                = $this->parent->parent;
				} else {
					return $this->as_text_node( $tag );
				}
			}

			$this->parent->_[ Constants::HDOM_INFO_END ] = $this->cursor;
			if ( $this->parent->parent ) {
				$this->parent = $this->parent->parent;
			}

			$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next

			return true;
		}

		$node                                  = new Node( $this );
		$node->_[ Constants::HDOM_INFO_BEGIN ] = $this->cursor;
		++ $this->cursor;
		$tag = $this->copy_until( $this->token_slash );

		// doctype, cdata & comments...
		if ( isset( $tag[0] ) && $tag[0] === '!' ) {
			$node->_[ Constants::HDOM_INFO_TEXT ] = '<' . $tag . $this->copy_until_char( '>' );

			if ( isset( $tag[2] ) && $tag[1] === '-' && $tag[2] === '-' ) {
				$node->nodetype = Constants::HDOM_TYPE_COMMENT;
				$node->tag      = 'comment';
			} else {
				$node->nodetype = Constants::HDOM_TYPE_UNKNOWN;
				$node->tag      = 'unknown';
			}

			if ( $this->char === '>' ) {
				$node->_[ Constants::HDOM_INFO_TEXT ] .= '>';
			}
			$this->link_nodes( $node, true );
			$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next

			return true;
		}

		// text
		if ( $pos = str_contains( $tag, '<' ) ) {
			$tag                                  = '<' . substr( $tag, 0, - 1 );
			$node->_[ Constants::HDOM_INFO_TEXT ] = $tag;
			$this->link_nodes( $node, false );
			$this->char = $this->doc[ -- $this->pos ]; // prev

			return true;
		}

		if ( ! preg_match( "/^[\w\-:]+$/", $tag ) ) {
			$node->_[ Constants::HDOM_INFO_TEXT ] = '<' . $tag . $this->copy_until( '<>' );
			if ( $this->char === '<' ) {
				$this->link_nodes( $node, false );

				return true;
			}

			if ( $this->char === '>' ) {
				$node->_[ Constants::HDOM_INFO_TEXT ] .= '>';
			}
			$this->link_nodes( $node, false );
			$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next

			return true;
		}

		// begin tag
		$node->nodetype = Constants::HDOM_TYPE_ELEMENT;
		$tag_lower      = strtolower( $tag );
		$node->tag      = ( $this->lowercase ) ? $tag_lower : $tag;

		// handle optional closing tags
		if ( isset( $this->optional_closing_tags[ $tag_lower ] ) ) {
			while ( isset( $this->optional_closing_tags[ $tag_lower ][ strtolower( $this->parent->tag ) ] ) ) {
				$this->parent->_[ Constants::HDOM_INFO_END ] = 0;
				$this->parent                                = $this->parent->parent;
			}
			$node->parent = $this->parent;
		}

		$guard = 0; // prevent infinity loop
		$space = array( $this->copy_skip( $this->token_blank ), '', '' );

		// attributes
		do {
			if ( $this->char !== null && $space[0] === '' ) {
				break;
			}
			$name = $this->copy_until( $this->token_equal );
			if ( $guard === $this->pos ) {
				$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
				continue;
			}
			$guard = $this->pos;

			// handle endless '<'
			if ( $this->pos >= $this->size - 1 && $this->char !== '>' ) {
				$node->nodetype                       = Constants::HDOM_TYPE_TEXT;
				$node->_[ Constants::HDOM_INFO_END ]  = 0;
				$node->_[ Constants::HDOM_INFO_TEXT ] = '<' . $tag . $space[0] . $name;
				$node->tag                            = 'text';
				$this->link_nodes( $node, false );

				return true;
			}

			// handle mismatch '<'
			if ( $this->doc[ $this->pos - 1 ] == '<' ) {
				$node->nodetype                       = Constants::HDOM_TYPE_TEXT;
				$node->tag                            = 'text';
				$node->attr                           = array();
				$node->_[ Constants::HDOM_INFO_END ]  = 0;
				$node->_[ Constants::HDOM_INFO_TEXT ] = substr( $this->doc, $begin_tag_pos, $this->pos - $begin_tag_pos - 1 );
				$this->pos                            -= 2;
				$this->char                           = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
				$this->link_nodes( $node, false );

				return true;
			}

			if ( $name !== '/' && $name !== '' ) {
				$space[1] = $this->copy_skip( $this->token_blank );
				$name     = $this->restore_noise( $name );
				if ( $this->lowercase ) {
					$name = strtolower( $name );
				}
				if ( $this->char === '=' ) {
					$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
					$this->parse_attr( $node, $name, $space );
				} else {
					//no value attr: nowrap, checked selected...
					$node->_[ Constants::HDOM_INFO_QUOTE ][] = Constants::HDOM_QUOTE_NO;
					$node->attr[ $name ]                     = true;
					if ( $this->char != '>' ) {
						$this->char = $this->doc[ -- $this->pos ];
					} // prev
				}
				$node->_[ Constants::HDOM_INFO_SPACE ][] = $space;
				$space                                   = array(
					$this->copy_skip( $this->token_blank ),
					'',
					''
				);
			} else {
				break;
			}
		} while ( $this->char !== '>' && $this->char !== '/' );

		$this->link_nodes( $node, true );
		$node->_[ Constants::HDOM_INFO_ENDSPACE ] = $space[0];

		// check self closing
		if ( $this->copy_until_char_escape( '>' ) === '/' ) {
			$node->_[ Constants::HDOM_INFO_ENDSPACE ] .= '/';
			$node->_[ Constants::HDOM_INFO_END ]      = 0;
		} else if ( ! isset( $this->self_closing_tags[ strtolower( $node->tag ) ] ) ) {
			// reset parent
			$this->parent = $node;
		}
		$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next

		return true;
	}

	// parse attributes
	protected function parse_attr( $node, $name, &$space ) {
		$space[2] = $this->copy_skip( $this->token_blank );
		switch ( $this->char ) {
			case '"':
				$node->_[ Constants::HDOM_INFO_QUOTE ][] = Constants::HDOM_QUOTE_DOUBLE;

				$this->char          = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
				$node->attr[ $name ] = $this->restore_noise( $this->copy_until_char_escape( '"' ) );
				$this->char          = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
				break;
			case '\'':
				$node->_[ Constants::HDOM_INFO_QUOTE ][] = Constants::HDOM_QUOTE_SINGLE;

				$this->char          = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
				$node->attr[ $name ] = $this->restore_noise( $this->copy_until_char_escape( '\'' ) );
				$this->char          = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
				break;
			default:
				$node->_[ Constants::HDOM_INFO_QUOTE ][] = Constants::HDOM_QUOTE_NO;

				$node->attr[ $name ] = $this->restore_noise( $this->copy_until( $this->token_attr ) );
		}
	}

	// link node's parent
	protected function link_nodes( $node, $is_child ) {
		$node->parent          = $this->parent;
		$this->parent->nodes[] = $node;
		if ( $is_child ) {
			$this->parent->children[] = $node;
		}
	}

	// as a text node
	protected function as_text_node( $tag ) {
		$node = new Node( $this );
		++ $this->cursor;
		$node->_[ Constants::HDOM_INFO_TEXT ] = '</' . $tag . '>';
		$this->link_nodes( $node, false );
		$this->char = ( ++ $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next

		return true;
	}

	protected function skip( $chars ) {
		$this->pos  += strspn( $this->doc, $chars, $this->pos );
		$this->char = ( $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
	}

	protected function copy_skip( $chars ) {
		$pos        = $this->pos;
		$len        = strspn( $this->doc, $chars, $pos );
		$this->pos  += $len;
		$this->char = ( $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next
		if ( $len === 0 ) {
			return '';
		}

		return substr( $this->doc, $pos, $len );
	}

	protected function copy_until( $chars ) {
		$pos        = $this->pos;
		$len        = strcspn( $this->doc, $chars, $pos );
		$this->pos  += $len;
		$this->char = ( $this->pos < $this->size ) ? $this->doc[ $this->pos ] : null; // next

		return substr( $this->doc, $pos, $len );
	}

	protected function copy_until_char( $char ) {
		if ( $this->char === null ) {
			return '';
		}

		if ( ( $pos = strpos( $this->doc, $char, $this->pos ) ) === false ) {
			$ret        = substr( $this->doc, $this->pos, $this->size - $this->pos );
			$this->char = null;
			$this->pos  = $this->size;

			return $ret;
		}

		if ( $pos === $this->pos ) {
			return '';
		}
		$pos_old    = $this->pos;
		$this->char = $this->doc[ $pos ];
		$this->pos  = $pos;

		return substr( $this->doc, $pos_old, $pos - $pos_old );
	}

	protected function copy_until_char_escape( $char ) {
		if ( $this->char === null ) {
			return '';
		}

		$start = $this->pos;
		while ( 1 ) {
			if ( ( $pos = strpos( $this->doc, $char, $start ) ) === false ) {
				$ret        = substr( $this->doc, $this->pos, $this->size - $this->pos );
				$this->char = null;
				$this->pos  = $this->size;

				return $ret;
			}

			if ( $pos === $this->pos ) {
				return '';
			}

			if ( $this->doc[ $pos - 1 ] === '\\' ) {
				$start = $pos + 1;
				continue;
			}

			$pos_old    = $this->pos;
			$this->char = $this->doc[ $pos ];
			$this->pos  = $pos;

			return substr( $this->doc, $pos_old, $pos - $pos_old );
		}
	}

	// remove noise from html content
	protected function remove_noise( $pattern, $remove_tag = false ) {
		$count = preg_match_all( $pattern, $this->doc, $matches, PREG_SET_ORDER | PREG_OFFSET_CAPTURE );
		//       logger ("meo:" . $pattern . $count);

		for ( $i = $count - 1; $i > - 1; -- $i ) {
			$key                 = '___noise___' . sprintf( '% 3d', count( $this->noise ) + 100 );
			$idx                 = ( $remove_tag ) ? 0 : 1;
			$this->noise[ $key ] = $matches[ $i ][ $idx ][0];
			$this->doc           = substr_replace( $this->doc, $key, $matches[ $i ][ $idx ][1], strlen( $matches[ $i ][ $idx ][0] ) );
		}

		// reset the length of content
		$this->size = strlen( $this->doc );
		if ( $this->size > 0 ) {
			$this->char = $this->doc[0];
		}
	}

	// restore noise to html content
	public function restore_noise( $text ) {
//        logger ("noise:".$text);
		while ( ( $pos = strpos( $text, '___noise___' ) ) !== false ) {
			$key = '___noise___' . $text[ $pos + 11 ] . $text[ $pos + 12 ] . $text[ $pos + 13 ];
			if ( isset( $this->noise[ $key ] ) ) {
				$text = substr( $text, 0, $pos ) . $this->noise[ $key ] . substr( $text, $pos + 14 );
			}
		}

		return $text;
	}

	public function __toString() {
		return $this->root->innertext();
	}

	public function __get( $name ) {
		switch ( $name ) {
			case 'outertext':
				return $this->root->innertext();
			case 'innertext':
				return $this->root->innertext();
			case 'plaintext':
				return $this->root->text();
		}
	}

	// camel naming conventions
	public function childNodes( $idx = - 1 ) {
		return $this->root->childNodes( $idx );
	}

	public function firstChild() {
		return $this->root->first_child();
	}

	public function lastChild() {
		return $this->root->last_child();
	}

	public function getElementById( $id ) {
		return $this->find( "#$id", 0 );
	}

	public function getElementsById( $id, $idx = null ) {
		return $this->find( "#$id", $idx );
	}

	public function getElementByTagName( $name ) {
		return $this->find( $name, 0 );
	}

	public function getElementsByTagName( $name, $idx = - 1 ) {
		return $this->find( $name, $idx );
	}

	public function loadFile() {
		$args = func_get_args();
		$this->load( file_get_contents( ...$args ), true );
	}

}