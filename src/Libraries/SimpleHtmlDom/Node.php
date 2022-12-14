<?php

namespace OpenTransposh\Libraries\SimpleHtmlDom;

class Node {

	public $nodetype = Constants::HDOM_TYPE_TEXT;
	public $tag = 'text';
	public $attr = array();
	public $children = array();
	public $nodes = array();
	public $parent = null;
	public $_ = array();
	private $dom = null;

	public function __construct( $dom ) {
		$this->dom    = $dom;
		$dom->nodes[] = $this;
	}

	public function __destruct() {
		$this->clear();
	}

	public function __toString() {
		return $this->outertext();
	}

	// clean up memory due to php5 circular references memory leak...
	public function clear() {
		$this->dom      = null;
		$this->nodes    = null;
		$this->parent   = null;
		$this->children = null;
	}

	// dump node's tree
	public function dump( $show_attr = true ) {
		$this->dump_html_tree_transposh( $this, $show_attr );
	}

	public function dump_html_tree_transposh( $node, $show_attr = true, $deep = 0 ) {
		$lead = str_repeat( '    ', $deep );
		echo $lead . $node->tag;
		if ( $show_attr && count( $node->attr ) > 0 ) {
			echo '(';
			foreach ( $node->attr as $k => $v ) {
				echo "[$k]=>\"" . $node->$k . '", ';
			}
			echo ')';
		}
		echo "\n";

		foreach ( $node->nodes as $c ) {
			$this->dump_html_tree_transposh( $c, $show_attr, $deep + 1 );
		}
	}

	// returns the parent of node
	public function parent() {
		return $this->parent;
	}

	// returns children of node
	public function children( $idx = - 1 ) {
		if ( $idx === - 1 ) {
			return $this->children;
		}

		return $this->children[ $idx ] ?? null;
	}

	// returns the first child of node
	public function first_child() {
		if ( count( $this->children ) > 0 ) {
			return $this->children[0];
		}

		return null;
	}

	// returns the last child of node
	public function last_child() {
		if ( ( $count = count( $this->children ) ) > 0 ) {
			return $this->children[ $count - 1 ];
		}

		return null;
	}

	// returns the next sibling of node
	public function next_sibling() {
		if ( $this->parent === null ) {
			return null;
		}
		$idx   = 0;
		$count = count( $this->parent->children );
		while ( $idx < $count && $this !== $this->parent->children[ $idx ] ) {
			++ $idx;
		}
		if ( ++ $idx >= $count ) {
			return null;
		}

		return $this->parent->children[ $idx ];
	}

	// returns the previous sibling of node
	public function prev_sibling() {
		if ( $this->parent === null ) {
			return null;
		}
		$idx   = 0;
		$count = count( $this->parent->children );
		while ( $idx < $count && $this !== $this->parent->children[ $idx ] ) {
			++ $idx;
		}
		if ( -- $idx < 0 ) {
			return null;
		}

		return $this->parent->children[ $idx ];
	}

	// get dom node's inner html
	public function innertext() {
		if ( isset( $this->_[ Constants::HDOM_INFO_INNER ] ) ) {
			return $this->_[ Constants::HDOM_INFO_INNER ];
		}
		if ( isset( $this->_[ Constants::HDOM_INFO_TEXT ] ) ) {
			return $this->dom->restore_noise( $this->_[ Constants::HDOM_INFO_TEXT ] );
		}

		$ret = '';
		foreach ( $this->nodes as $n ) {
			$ret .= $n->outertext();
		}

		return $ret;
	}

	// get dom node's outer text (with tag)
	public function outertext() {
		if ( $this->tag === 'root' ) {
			return $this->innertext();
		}

		// trigger callback
		if ( $this->dom->callback !== null ) {
			call_user_func_array( $this->dom->callback, array( $this ) );
		}

		if ( isset( $this->_[ Constants::HDOM_INFO_OUTER ] ) ) {
			return $this->_[ Constants::HDOM_INFO_OUTER ];
		}
		if ( isset( $this->_[ Constants::HDOM_INFO_TEXT ] ) ) {
			return $this->dom->restore_noise( $this->_[ Constants::HDOM_INFO_TEXT ] );
		}

		// render begin tag
		$ret = $this->dom->nodes[ $this->_[ Constants::HDOM_INFO_BEGIN ] ]->makeup();

		// render inner text
		if ( isset( $this->_[ Constants::HDOM_INFO_INNER ] ) ) {
			$ret .= $this->_[ Constants::HDOM_INFO_INNER ];
		} else {
			foreach ( $this->nodes as $n ) {
				$ret .= $n->outertext();
			}
		}

		// render end tag
		if ( isset( $this->_[ Constants::HDOM_INFO_END ] ) && $this->_[ Constants::HDOM_INFO_END ] != 0 ) {
			$ret .= '</' . $this->tag . '>';
		}

		return $ret;
	}

	// get dom node's plain text
	public function text() {
		if ( isset( $this->_[ Constants::HDOM_INFO_INNER ] ) ) {
			return $this->_[ Constants::HDOM_INFO_INNER ];
		}
		switch ( $this->nodetype ) {
			case Constants::HDOM_TYPE_TEXT:
				return $this->dom->restore_noise( $this->_[ Constants::HDOM_INFO_TEXT ] );
			case Constants::HDOM_TYPE_COMMENT:
				return '';
			case Constants::HDOM_TYPE_UNKNOWN:
				return '';
		}
		if ( strcasecmp( $this->tag, 'script' ) === 0 ) {
			return '';
		}
		if ( strcasecmp( $this->tag, 'style' ) === 0 ) {
			return '';
		}

		$ret = '';
		foreach ( $this->nodes as $n ) {
			$ret .= $n->text();
		}

		return $ret;
	}

	public function xmltext() {
		$ret = $this->innertext();
		$ret = str_ireplace( '<![CDATA[', '', $ret );

		return str_replace( ']]>', '', $ret );
	}

	// build node's text with tag
	public function makeup() {
		// text, comment, unknown
		if ( isset( $this->_[ Constants::HDOM_INFO_TEXT ] ) ) {
			return $this->dom->restore_noise( $this->_[ Constants::HDOM_INFO_TEXT ] );
		}

		$ret = '<' . $this->tag;
		$i   = - 1;

		foreach ( $this->attr as $key => $val ) {
			++ $i;

			// skip removed attribute
			if ( $val === null || $val === false ) {
				continue;
			}

			$ret .= $this->_[ Constants::HDOM_INFO_SPACE ][ $i ][0];
			//no value attr: nowrap, checked selected...
			if ( $val === true ) {
				$ret .= $key;
			} else {
				$quote = match ( $this->_[ Constants::HDOM_INFO_QUOTE ][ $i ] ) {
					Constants::HDOM_QUOTE_DOUBLE => '"',
					Constants::HDOM_QUOTE_SINGLE => '\'',
					default => '',
				};
				$ret .= $key . $this->_[ Constants::HDOM_INFO_SPACE ][ $i ][1] . '=' . $this->_[ Constants::HDOM_INFO_SPACE ][ $i ][2] . $quote . $val . $quote;
			}
		}
		$ret = $this->dom->restore_noise( $ret );

		return $ret . $this->_[ Constants::HDOM_INFO_ENDSPACE ] . '>';
	}

	// find elements by css selector
	public function find( $selector, $idx = null ) {
		$selectors = $this->parse_selector( $selector );
		if ( ( $count = count( $selectors ) ) === 0 ) {
			return array();
		}
		$found_keys = array();

		// find each selector
		for ( $c = 0; $c < $count; ++ $c ) {
			if ( ( $levle = count( $selectors[0] ) ) === 0 ) {
				return array();
			}
			if ( ! isset( $this->_[ Constants::HDOM_INFO_BEGIN ] ) ) {
				return array();
			}

			$head = array( $this->_[ Constants::HDOM_INFO_BEGIN ] => 1 );

			// handle descendant selectors, no recursive!
			for ( $l = 0; $l < $levle; ++ $l ) {
				$ret = array();
				foreach ( $head as $k => $v ) {
					$n = ( $k === - 1 ) ? $this->dom->root : $this->dom->nodes[ $k ];
					$n->seek( $selectors[ $c ][ $l ], $ret );
				}
				$head = $ret;
			}

			foreach ( $head as $k => $v ) {
				if ( ! isset( $found_keys[ $k ] ) ) {
					$found_keys[ $k ] = 1;
				}
			}
		}

		// sort keys
		ksort( $found_keys );

		$found = array();
		foreach ( $found_keys as $k => $v ) {
			$found[] = $this->dom->nodes[ $k ];
		}

		// return nth-element or array
		if ( is_null( $idx ) ) {
			return $found;
		} else if ( $idx < 0 ) {
			$idx = count( $found ) + $idx;
		}

		return $found[ $idx ] ?? null;
	}

	// seek for given conditions
	protected function seek( $selector, &$ret ) {
		[ $tag, $key, $val, $exp, $no_key ] = $selector;

		// xpath index
		if ( $tag && $key && is_numeric( $key ) ) {
			$count = 0;
			foreach ( $this->children as $c ) {
				if ( $tag === '*' || $tag === $c->tag ) {
					if ( ++ $count == $key ) {
						$ret[ $c->_[ Constants::HDOM_INFO_BEGIN ] ] = 1;

						return;
					}
				}
			}

			return;
		}

		$end = ( ! empty( $this->_[ Constants::HDOM_INFO_END ] ) ) ? $this->_[ Constants::HDOM_INFO_END ] : 0;
		if ( $end == 0 ) {
			$parent = $this->parent;
			while ( ! isset( $parent->_[ Constants::HDOM_INFO_END ] ) && $parent !== null ) {
				$end    -= 1;
				$parent = $parent->parent;
			}
			$end += $parent->_[ Constants::HDOM_INFO_END ];
		}

		for ( $i = $this->_[ Constants::HDOM_INFO_BEGIN ] + 1; $i < $end; ++ $i ) {
			$node = $this->dom->nodes[ $i ];
			$pass = true;

			if ( $tag === '*' && ! $key ) {
				if ( in_array( $node, $this->children, true ) ) {
					$ret[ $i ] = 1;
				}
				continue;
			}

			// compare tag
			if ( $tag && $tag != $node->tag && $tag !== '*' ) {
				$pass = false;
			}
			// compare key
			if ( $pass && $key ) {
				if ( $no_key ) {
					if ( isset( $node->attr[ $key ] ) ) {
						$pass = false;
					}
				} else if ( ! isset( $node->attr[ $key ] ) ) {
					$pass = false;
				}
			}
			// compare value
			if ( $pass && $key && $val && $val !== '*' ) {
				$check = $this->match( $exp, $val, $node->attr[ $key ] );
				// handle multiple class
				if ( ! $check && strcasecmp( $key, 'class' ) === 0 ) {
					foreach ( explode( ' ', $node->attr[ $key ] ) as $k ) {
						$check = $this->match( $exp, $val, $k );
						if ( $check ) {
							break;
						}
					}
				}
				if ( ! $check ) {
					$pass = false;
				}
			}
			if ( $pass ) {
				$ret[ $i ] = 1;
			}
			unset( $node );
		}
	}

	protected function match( $exp, $pattern, $value ) {
		switch ( $exp ) {
			case '=':
				return ( $value === $pattern );
			case '!=':
				return ( $value !== $pattern );
			case '^=':
				return preg_match( "/^" . preg_quote( $pattern, '/' ) . "/", $value );
			case '$=':
				return preg_match( "/" . preg_quote( $pattern, '/' ) . "$/", $value );
			case '*=':
				if ( $pattern[0] == '/' ) {
					return preg_match( $pattern, $value );
				}

				return preg_match( "/" . $pattern . "/i", $value );
		}

		return false;
	}

	protected function parse_selector( $selector_string ) {
		// pattern of CSS selectors, modified from mootools
		$pattern = "/([\w\-:\*]*)(?:\#([\w\-]+)|\.([\w\-]+))?(?:\[@?(!?[\w\-:]+)(?:([!*^$]?=)[\"']?(.*?)[\"']?)?\])?([\/, ]+)/is"; // new version
		preg_match_all( $pattern, trim( $selector_string ) . ' ', $matches, PREG_SET_ORDER );
		$selectors = array();
		$result    = array();
		//print_r($matches);

		foreach ( $matches as $m ) {
			$m[0] = trim( $m[0] );
			if ( $m[0] === '' || $m[0] === '/' || $m[0] === '//' ) {
				continue;
			}
			// for borwser grnreated xpath
			if ( $m[1] === 'tbody' ) {
				continue;
			}

			[ $tag, $key, $val, $exp, $no_key ] = array( $m[1], null, null, '=', false );
			if ( ! empty( $m[2] ) ) {
				$key = 'id';
				$val = $m[2];
			}
			if ( ! empty( $m[3] ) ) {
				$key = 'class';
				$val = $m[3];
			}
			if ( ! empty( $m[4] ) ) {
				$key = $m[4];
			}
			if ( ! empty( $m[5] ) ) {
				$exp = $m[5];
			}
			if ( ! empty( $m[6] ) ) {
				$val = $m[6];
			}

			// convert to lowercase
			if ( $this->dom->lowercase ) {
				$tag = strtolower( $tag );
				$key = strtolower( $key );
			}
			//elements that do NOT have the specified attribute
			if ( isset( $key[0] ) && $key[0] === '!' ) {
				$key    = substr( $key, 1 );
				$no_key = true;
			}

			$result[] = array( $tag, $key, $val, $exp, $no_key );
			if ( trim( $m[7] ) === ',' ) {
				$selectors[] = $result;
				$result      = array();
			}
		}
		if ( count( $result ) > 0 ) {
			$selectors[] = $result;
		}

		return $selectors;
	}

	public function __get( $name ) {
		return $this->attr[ $name ] ?? match ( $name ) {
			'outertext' => $this->outertext(),
			'innertext' => $this->innertext(),
			'plaintext' => $this->text(),
			'xmltext' => $this->xmltext(),
			default => array_key_exists( $name, $this->attr ),
		};
	}

	public function __set( $name, $value ) {
		if ( 'outertext' === $name ) {
			return $this->_[ Constants::HDOM_INFO_OUTER ] = $value;
		}

		if ( 'innertext' === $name ) {
			if ( isset( $this->_[ Constants::HDOM_INFO_TEXT ] ) ) {
				return $this->_[ Constants::HDOM_INFO_TEXT ] = $value;
			}

			return $this->_[ Constants::HDOM_INFO_INNER ] = $value;
		}
		if ( ! isset( $this->attr[ $name ] ) ) {
			$this->_[ Constants::HDOM_INFO_SPACE ][] = array(
				' ',
				'',
				''
			);
			$this->_[ Constants::HDOM_INFO_QUOTE ][] = Constants::HDOM_QUOTE_DOUBLE;
		}
		$this->attr[ $name ] = $value;
	}

	/**
	 * If no value attr: nowrap, checked selected...
	 *
	 * @param $name
	 *
	 * @return bool
	 */
	public function __isset( $name ) {
		return match ( $name ) {
			'outertext', 'innertext', 'plaintext' => true,
			default => array_key_exists( $name, $this->attr ) || isset( $this->attr[ $name ] ),
		};
	}

	public function __unset( $name ) {
		if ( isset( $this->attr[ $name ] ) ) {
			unset( $this->attr[ $name ] );
		}
	}

	// camel naming conventions
	public function getAllAttributes() {
		return $this->attr;
	}

	public function getAttribute( $name ) {
		return $this->__get( $name );
	}

	public function setAttribute( $name, $value ) {
		$this->__set( $name, $value );
	}

	public function hasAttribute( $name ) {
		return $this->__isset( $name );
	}

	public function removeAttribute( $name ) {
		$this->__set( $name, null );
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

	public function getElementsByTagName( $name, $idx = null ) {
		return $this->find( $name, $idx );
	}

	public function parentNode() {
		return $this->parent();
	}

	public function childNodes( $idx = - 1 ) {
		return $this->children( $idx );
	}

	public function firstChild() {
		return $this->first_child();
	}

	public function lastChild() {
		return $this->last_child();
	}

	public function nextSibling() {
		return $this->next_sibling();
	}

	public function previousSibling() {
		return $this->prev_sibling();
	}

}