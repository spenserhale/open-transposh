<?php

namespace OpenTransposh;

use OpenTransposh\Core\Utilities;
use OpenTransposh\Logging\LogService;
use Memcache;

class Database {
	const TRANSPOSH_OPTIONS_DBSETUP = 'transposh_inside_dbupgrade';

	/** @var Plugin father class */
	private $transposh;

	/** @var array holds prefetched translations */
	private $translations;

	/** @var string translation table name */
	private $translation_table;

	/** @var string translation log table name */
	private $translation_log_table;

	/** @var bool is memcached working */
	private $memcache_working = false;

	/** @var Memcache the memcached connection object */
	private $memcache;

	/**
	 * PHP5+ only
	 */
	function __construct( &$transposh ) {
		$this->transposh             = &$transposh;
		$this->translation_table     = $GLOBALS['wpdb']->prefix . TRANSLATIONS_TABLE;
		$this->translation_log_table = $GLOBALS['wpdb']->prefix . TRANSLATIONS_LOG;

		if ( class_exists( Memcache::class ) ) {
			if ( $this->transposh->options->debug_enable ) {
				LogService::legacy_log( 'Trying pecl-Memcache!', 3 );
			}
			$this->memcache_working = true;
			$this->memcache         = new Memcache;
			@$this->memcache->connect( TP_MEMCACHED_SRV, TP_MEMCACHED_PORT ) or $this->memcache_working = false;
			if ( $this->transposh->options->debug_enable && $this->memcache_working ) {
				LogService::legacy_log( 'Memcache seems working' );
			}
		}
	}

	/**
	 * Function to return a value from memory cache
	 *
	 * @param string $original string we want translated
	 * @param string $lang language we want it translated to
	 *
	 * @return array|null array with translation or null on cache miss
	 */
	function cache_fetch( string $original, string $lang ): ?array {
		if ( ! TP_ENABLE_CACHE ) {
			return null;
		}
		$key = $lang . '_' . $original;
		if ( $this->memcache_working ) {
			$cached = $this->memcache->get( $key );
			LogService::legacy_log( "memcached $key $cached", 5 );
		} elseif ( function_exists( 'apc_fetch' ) ) {
			$cached = apc_fetch( $key, $rc );
			if ( $rc === false ) {
				return null;
			}
			LogService::legacy_log( "apc $key $cached", 5 );
		} elseif ( function_exists( 'apcu_fetch' ) ) {
			$cached = apcu_fetch( $key, $rc );
			if ( $rc === false ) {
				return null;
			}
			LogService::legacy_log( "apcu $key $cached", 5 );
		} else {
            $cached = null;
        }
		LogService::legacy_log( "Cache fetched: $original => $cached", 4 );

        return match (true) {
            is_array( $cached ) => $cached,
            is_string( $cached ) => (array) explode( '_', $cached, 2 ),
            default => null
        };
    }

	/**
	 * Function to store translation in memory cache
	 *
	 * @param string $original
	 * @param string $lang
	 * @param array $translated
	 * @param int $ttl time to live in the cache
	 *
	 * @return bool true if stored successfully
	 */
	function cache_store( $original, $lang, $translated, $ttl ) {
		if ( ! TP_ENABLE_CACHE ) {
			return false;
		}
		$key = $lang . '_' . $original;
		if ( $translated !== null ) {
			$translated = implode( '_', $translated );
		}
		$rc = false;
		if ( $this->memcache_working ) {
			$rc = $this->memcache->set( $key, $translated ); //, time() + $ttl);
		} elseif ( function_exists( 'apc_store' ) ) {
			$rc = apc_store( $key, $translated, $ttl );
		} elseif ( function_exists( 'apcu_store' ) ) {
			$rc = apcu_store( $key, $translated, $ttl );
		} elseif ( function_exists( 'xcache_set' ) ) {
			$rc = @xcache_set( $key, $translated, $ttl );
		} elseif ( function_exists( 'eaccelerator_put' ) ) {
			$rc = eaccelerator_put( $key, $translated, $ttl );
		}

		if ( $rc ) {
			LogService::legacy_log( "Stored in cache: $original => {$translated}", 4 );
		} else {
			LogService::legacy_log( "Didn't cache: $original => {$translated}", 4 );
		}

		return $rc;
	}

	/**
	 * Remove a value from memory cache
	 *
	 * @param string $original
	 * @param string $lang
	 */
	function cache_delete( $original, $lang ) {
		if ( ! TP_ENABLE_CACHE ) {
			return;
		}
		$key = $lang . '_' . $original;
		if ( $this->memcache_working ) {
			$this->memcache->delete( $key );
		} elseif ( function_exists( 'apc_delete' ) ) {
			apc_delete( $key );
		} elseif ( function_exists( 'apcu_delete' ) ) {
			apcu_delete( $key );
		} elseif ( function_exists( 'xcache_unset' ) ) {
			@xcache_unset( $key );
		} elseif ( function_exists( 'eaccelerator_rm' ) ) {
			eaccelerator_rm( $key );
		}
	}

	/**
	 * Clean the memory cache
	 */
	function cache_clean() {
		if ( ! TP_ENABLE_CACHE ) {
			return;
		}
		if ( $this->memcache_working ) {
			$this->memcache->flush();
		} elseif ( function_exists( 'apc_clear_cache' ) ) {
			apc_clear_cache( 'user' );
		} elseif ( function_exists( 'apc_clear_cache' ) ) {
			apc_clear_cache();
		} elseif ( function_exists( 'xcache_unset_by_prefix' ) ) {
			@xcache_unset_by_prefix();
		}
		//TODO - clean on eaccelerator is not so clean...
	}

	/**
	 * Allow fetching of multiple translation requests from the database with a single query
	 *
	 * @param array $originals keys hold the strings...
	 * @param string $lang
	 */
	function prefetch_translations( $originals, $lang ) {
		if ( ! $originals ) {
			return;
		}
		LogService::legacy_log( $originals, 4 );
		$where = '';
		foreach ( $originals as $original ) {
			$original = esc_sql( html_entity_decode( $original, ENT_NOQUOTES, 'UTF-8' ) );
			$cached   = $this->cache_fetch( $original, $lang );
			// if $cached is not null, there is something in the cache, so no need to prefetch
			if ( $cached !== null ) {
				continue;
			}
			$where .= ( ( $where ) ? ' OR ' : '' ) . "original = '$original'";
		}
		// make sure $lang is reasonable, unless someone is messing with us, it will be ok
		if ( ! ( $this->transposh->options->is_active_language( $lang ) ) ) {
			return;
		}

		// If we have nothing, we will do nothing
		if ( ! $where ) {
			return;
		}
		$query = "SELECT original, translated, source FROM {$this->translation_table} WHERE ($where) and lang = '$lang' ";
		$rows  = $GLOBALS['wpdb']->get_results( $query, ARRAY_A );
		if ( empty( $rows ) ) {
			return;
		}
		// we are saving in the array and not directly to cache, because cache might not exist...
		foreach ( $rows as $row ) {
			// we are making sure to use the escaped version, because that is what we'll ask about
			$ro = esc_sql( html_entity_decode( $row['original'], ENT_NOQUOTES, 'UTF-8' ) );
			//$this->translations[$ro] = array($row['source'], stripslashes($row['translated']));
			$this->translations[ $ro ] = array(
				$row['source'],
				str_replace( '&amp;nbsp;', '&nbsp;', stripslashes( $row['translated'] ) )
			);
		}
		LogService::legacy_log( 'prefetched: ' . count( $this->translations ), 5 );
	}

	/**
	 * Fetch translation from db or cache.
	 * Returns An array that contains the translated string and it source.
	 * Will return NULL if no translation is available.
	 *
	 * @param string $orig
	 * @param string $lang
	 *
	 * @return array list(source,translation)
	 */
	function fetch_translation( $orig, $lang ) {
		$translated = null;
		LogService::legacy_log( "Fetching for: $orig-$lang", 4 );
		//The original is saved in db in its escaped form
		$original = esc_sql( html_entity_decode( $orig, ENT_NOQUOTES, 'UTF-8' ) );
		// first we look in the cache
		$cached = $this->cache_fetch( $original, $lang );
		if ( $cached !== null) {
			LogService::legacy_log( "Exit from cache: $cached[0] $cached[1]", 4 );

			return $cached;
		}
		// then we look for a prefetch
		if ( isset( $this->translations[ $original ] ) ) {
			$translated = $this->translations[ $original ];
			LogService::legacy_log( "prefetch result for $original >>> {$this->translations[$original][0]} ({$this->translations[$original][1]})",
				4 );
		} else {
			// make sure $lang is reasonable, unless someone is messing with us, it will be ok
			if ( ! ( $this->transposh->options->is_active_language( $lang ) ) ) {
				return;
			}
			$query = "SELECT translated, source FROM {$this->translation_table} WHERE original = '$original' and lang = '$lang' ";
			$row   = $GLOBALS['wpdb']->get_row( $query );

			if ( $row !== null ) {
				$translated_text = stripslashes( $row->translated );
				$translated      = array( $row->source, $translated_text );
				LogService::legacy_log( "db result for $original >>> $translated_text ($lang) ({$row->source})",
					4 );
			}
		}
		// we can store the result in the cache (or the fact we don't have one)
		$this->cache_store( $original, $lang, $translated, TP_CACHE_TTL );

		return $translated;
	}

	/**
	 * Fetch original from db or cache.
	 * Returns the original for a given translation.
	 * Will return NULL if no translation is available.
	 *
	 * @param string $trans
	 * @param string $lang
	 *
	 * @return string $original
	 */
	function fetch_original( $trans, $lang ) {
		$original = null;
		LogService::legacy_log( "Enter: $trans", 4 );

		// The translation is saved in db in its escaped form
		$translation = esc_sql( html_entity_decode( $trans, ENT_NOQUOTES, 'UTF-8' ) );
		// The translation might be cached (notice the additional postfix)
        [ $rev, $cached ] = $this->cache_fetch( 'R_' . $translation, $lang ) ?? [null, null];
		if ( $rev === 'r' ) {
			LogService::legacy_log( "Exit from cache: $translation $cached", 4 );

			return $cached;
		}
		// lang
		// FIXME - no prefetching for originals yet...
		if ( isset( $this->translations ) && $this->translations[ $translation ] ) {
			$original = $this->translations[ $translation ];
			LogService::legacy_log( "prefetch result for $translation >>> {$this->translations[$translation][0]} ({$this->translations[$translation][1]})",
				3 );
		} else {
			$query = "SELECT original FROM {$this->translation_table} WHERE translated = '$translation' and lang = '$lang' ";
			$row   = $GLOBALS['wpdb']->get_row( $query );

			if ( $row !== null ) {
				$original = stripslashes( $row->original );
				LogService::legacy_log( "db result for $translation >>> $original ($lang)", 4 );
			}
		}

		// we can store the result in the cache (or the fact we don't have one)
		$this->cache_store( 'R_' . $translation, $lang, array( 'r', $original ), TP_CACHE_TTL );

		LogService::legacy_log( "Exit: $translation/$original", 4 );

		return $original;
	}

	/**
	 * A new translation has been posted, update the translation database.
	 * This has changed since we now accept multiple translations at once
	 * This function accepts a new more "versatile" format
	 * TODO - return some info?
	 * @global <type> $user_ID - TODO
	 */
	function update_translation( $by = "" ): ?array {

		$ref    = getenv( 'HTTP_REFERER' );
		$items  = $_POST['items'];
		$lang   = $_POST['ln0'];
		$source = $_POST['sr0'];
		// check params
		LogService::legacy_log( "Enter " . __FILE__ . " Params: $items, $lang, $ref", 5 );
		if ( ! isset( $items ) || ! isset( $lang ) ) {
			LogService::legacy_log( "Enter " . __FILE__ . " missing Params: $items, $lang, $ref", 1 );

			return null;
		}

		//Check permissions, first the lanugage must be on the edit list. Then either the user
		//is a translator or automatic translation if it is enabled.
		// we must check that all sent languages are editable
		$all_editable = true;
		for ( $i = 0; $i < $items; $i ++ ) {
			if ( isset( $_POST["ln$i"] ) && ! $this->transposh->options->is_active_language( $_POST["ln$i"] ) ) {
				$all_editable = false;
				break;
			}
		}
		if ( ! $by && ! ( $all_editable &&
		                  ( $this->transposh->is_translator() || ( $source > 0 && $this->transposh->options->enable_autotranslate ) ) ) ) {
			LogService::legacy_log( "Unauthorized translation attempt " . Utilities::get_clean_server_var( 'REMOTE_ADDR' ),
				1 );
			header( "HTTP/1.0 401 Unauthorized translation" );
			exit;
		}

		//add our own custom header - so we will know that we got here
		header( "Open Transposh: v-" . TRANSPOSH_PLUGIN_VER . " db_version-" . DB_VERSION );

		// translation log stuff, log either by param, user id, or ip
		$loguser = $by;
		if ( ! $loguser ) {
			$loguser = get_current_user_id();
		}
		if ( ! $loguser ) {
			$loguser = Utilities::get_clean_server_var( 'REMOTE_ADDR' );
		}

		// reset values (for good code style)
		$values                    = '';
		$delvalues                 = '';
		$backup_immidiate_possible = false;
		$firstitem                 = true;
		$alreadybatched            = array();
		// We are now processing all posted items
		for ( $i = 0; $i < $items; $i ++ ) {
			if ( isset( $_POST["tk$i"] ) ) {
//                $original = OpenTransposh\Core\transposh_utils::base64_url_decode($_POST["tk$i"]);
				$orig = stripslashes( $_POST["tk$i"] );
				// The original content is encoded as base64 before it is sent (i.e. token), after we
				// decode it should just the same after it was parsed.
				$original = esc_sql( htmlspecialchars( html_entity_decode( $orig, ENT_NOQUOTES, 'UTF-8' ) ) );
			}
			if ( isset( $_POST["tr$i"] ) ) {
				$trans = $_POST["tr$i"];
				// Decode & remove already escaped character to avoid double escaping
				$translation = esc_sql( htmlspecialchars( stripslashes( rawurldecode( $trans ) ) ) );
			}
			if ( isset( $_POST["ln$i"] ) ) {
				$lang = $_POST["ln$i"];
			}
			if ( isset( $_POST["sr$i"] ) ) {
				$source = $_POST["sr$i"];
			}

			// we attempt to avoid
			if ( isset( $alreadybatched[ $original . '---' . $lang ] ) ) {
				LogService::legacy_log( "Warning same item appeared twice in batch: $original $lang", 1 );
				continue;
			}
			$alreadybatched[ $original . '---' . $lang ] = true;
			// should we backup? - yes on any human translation
			if ( $source == 0 ) {
				$backup_immidiate_possible = true;
			}

			//Here we check we are not redoing stuff - and avoid escaping twice!!
			[ $old_source, $translated_text ] = $this->fetch_translation( $orig, $lang );
			if ( $translated_text ) {
				if ( $source > 0 ) {
					LogService::legacy_log( "Warning auto-translation for already translated: $original $lang, $old_source - $translated_text",
						1 );
					continue;
					//return; // too harsh, we just need to get to the next in for
				}
				if ( $translation == esc_sql( htmlspecialchars( stripslashes( rawurldecode( $translated_text ) ) ) ) && $old_source == $source ) {
					LogService::legacy_log( "Warning attempt to retranslate with same text: $original, $translation",
						1 );
					continue;
					//return; // too harsh, we just need to get to the next in for
				}
			}
			// Setting the values string for the database (notice how concatanation is handled)
			$delvalues .= ( $firstitem ? '' : ' OR ' ) . "(original ='$original' AND lang='$lang')";
			// Setting the transaction log records
			$values .= ( $firstitem ? '' : ', ' ) . "('" . $original . "','" . $translation . "','" . $lang . "','" . $loguser . "','" . $source . "')";

			// If we have caching - we remove previous entry from cache
			$this->cache_delete( $original, $lang );
			$firstitem = false;
			// TODO - maybe store value here?
		}

		// avoid empty database work
		if ( ! $values ) {
			return null;
		}
		// First we copy what we will overwrite to the log
		$copytolog = "INSERT INTO {$this->translation_log_table} (original, translated, lang, translated_by, source, timestamp) " .
		             "SELECT original, translated, lang, translated_by, source, timestamp " .
		             "FROM {$this->translation_table} " .
		             "WHERE $delvalues";
		LogService::legacy_log( $copytolog, 3 );
		$copyresult = $GLOBALS['wpdb']->query( $copytolog );
		if ( $copyresult === false ) {
			LogService::legacy_log( $GLOBALS['wpdb']->last_error, 1 );
			LogService::legacy_log( "Error !!! failed to move to log $delvalues,", 1 );
			header( "HTTP/1.0 404 Failed to update language database " . $GLOBALS['wpdb']->last_error );
		}

		// now we remove existing values
		$delfrommain = "DELETE FROM " . $this->translation_table . " WHERE $delvalues";
		LogService::legacy_log( $delfrommain, 3 );
		$delresult = $GLOBALS['wpdb']->query( $delfrommain );
		if ( $delresult === false ) {
			LogService::legacy_log( $GLOBALS['wpdb']->last_error, 1 );
			LogService::legacy_log( "Error !!! failed remove $delvalues,", 1 );
			header( "HTTP/1.0 404 Failed to update language database " . $GLOBALS['wpdb']->last_error );
		}

		// and finally - insert new ones
		$addnewtrans = "INSERT INTO " . $this->translation_table . " (original, translated, lang, translated_by, source) VALUES $values"; //TODO!!
		LogService::legacy_log( $addnewtrans, 3 );
		$addresult = $GLOBALS['wpdb']->query( $addnewtrans );

		// if the insertion worked, we will update the translation log
		if ( $addresult !== false ) {
			LogService::legacy_log( "Inserted to db '$values'", 3 );
		} else {
			LogService::legacy_log( $GLOBALS['wpdb']->last_error, 1 );
			LogService::legacy_log( "Error !!! failed to insert to db $original , $translation, $lang,", 1 );
			header( "HTTP/1.0 404 Failed to update language database " . $GLOBALS['wpdb']->last_error );
		}

		// if its a human translation we will call the action, this takes the assumption of a single human translation in
		// a function call, which should probably be verified (FIXME move up?)
		if ( $source == 0 ) {
			do_action( 'transposh_human_translation', $translation, $original, $lang, $loguser );
		}

		// TODO: move this to an action
		// Should we backup now?
		if ( $backup_immidiate_possible && $this->transposh->options->transposh_backup_schedule == 2 ) {
			return $this->transposh->run_backup();
		}
		// this is a termination for the ajax sequence
		//exit;
		return null;
	}

	/*
	 * Get translation history for some translation.
	 */
	public function get_translation_history( $token, $lang ): array {
		global $wpdb;

		// The original content is encoded as base64 before it is sent (i.e. token), after we
		// decode it should just the same after it was parsed.
		$original = esc_sql( html_entity_decode( $token, ENT_NOQUOTES, 'UTF-8' ) );
		$lang     = esc_sql( $lang );

		$query = <<<SQL
SELECT translated, translated_by, timestamp, source, user_login
FROM {$this->translation_log_table}
	LEFT JOIN {$wpdb->prefix}users ON translated_by = {$wpdb->prefix}users.id
	WHERE original='$original' AND lang='$lang'
	UNION SELECT translated, translated_by, timestamp, source, user_login 
    FROM {$this->translation_table} 
    	LEFT JOIN {$wpdb->prefix}users ON translated_by = {$wpdb->prefix}users.id
    	WHERE original='$original' AND lang='$lang'
ORDER BY timestamp DESC
SQL;
		LogService::legacy_log( "query is $query" );

		$rows = $wpdb->get_results( $query );
		foreach ( $rows as $row ) {
			if (
				( $row->translated_by == Utilities::get_clean_server_var( 'REMOTE_ADDR' ) && $row->source == '0' ) ||
				( is_user_logged_in() && current_user_can( TRANSLATOR ) ) ||
				current_user_can( 'manage_options' )
			) {
				$row->can_delete = true;
			}
		}

		return $rows;
	}

	/**
	 * Delete a specific translation history from the logs
	 *
	 * @param string $token
	 * @param string $lang
	 * @param string $timestamp
	 */
	//TODO: post this action to backup
	public function del_translation_history( $token, $langp, $timestampp ): bool {
		global $wpdb;
		$original  = esc_sql( html_entity_decode( $token, ENT_NOQUOTES, 'UTF-8' ) );
		$lang      = esc_sql( $langp );
		$timestamp = esc_sql( $timestampp );
		// first we look in the log table
		$in_log_table = false;
		$query        = <<<SQL
SELECT translated, translated_by, timestamp, source
FROM {$this->translation_log_table}
WHERE original='$original' AND lang='$lang' AND timestamp='$timestamp'
ORDER BY timestamp DESC
SQL;
		$rows         = $wpdb->get_results( $query );
		if ( ! empty( $rows ) ) {
			LogService::legacy_log( 'found in log' );
			$in_log_table = true;
		}
		// than we look in the main table, if its not found
		$in_main_table = false;
		if ( ! $in_log_table ) {
			$query = <<<SQL
SELECT translated, translated_by, timestamp, source
FROM {$this->translation_table}
WHERE original='$original' AND lang='$lang' AND timestamp='$timestamp'
ORDER BY timestamp DESC
SQL;
			$rows  = $wpdb->get_results( $query );
			if ( ! empty( $rows ) ) {
				LogService::legacy_log( 'found in mains' );
				$in_main_table = true;
			}
		}

		LogService::legacy_log( $query, 3 );
		// We only delete if we found something to delete and it is allowed to delete it (user either did that - by ip, has the translator role or is an admin)
		if (
			( $in_main_table || $in_log_table ) &&
			(
				( $rows[0]->translated_by == Utilities::get_clean_server_var( 'REMOTE_ADDR' ) &&
				  $rows[0]->source == '0' ) ||
				( is_user_logged_in() && current_user_can( TRANSLATOR ) ) ||
				current_user_can( 'manage_options' )
			)
		) {
			// delete faulty record, if in log
			if ( $in_log_table ) {
				$query = <<<SQL
DELETE FROM {$this->translation_log_table}
WHERE original='$original' AND lang='$lang' AND timestamp='$timestamp'
SQL;
				$wpdb->query( $query );
				LogService::legacy_log( $query, 3 );
			} else {
				// delete from main table
				$query = <<<SQL
DELETE FROM {$this->translation_table}
WHERE original='$original' AND lang='$lang'
SQL;
				$wpdb->query( $query );
				LogService::legacy_log( $query, 3 );

				// clear cache!
				$this->cache_delete( $original, $lang );
				// copy from log if possible.
				$query = <<<SQL
INSERT INTO {$this->translation_table} (original, translated, lang, translated_by, source, timestamp)
SELECT original, translated, lang, translated_by, source, timestamp
FROM {$this->translation_log_table}
WHERE original='$original' AND lang='$lang'
ORDER BY timestamp DESC LIMIT 1
SQL;
				$wpdb->query( $query );
				LogService::legacy_log( $query, 3 );

				//need to remove last from log now...
				$query = <<<SQL
DELETE {$this->translation_log_table}
FROM {$this->translation_log_table}
INNER JOIN {$this->translation_table}
ON {$this->translation_table}.original = {$this->translation_log_table}.original
AND {$this->translation_table}.lang = {$this->translation_log_table}.lang
AND {$this->translation_table}.translated = {$this->translation_log_table}.translated
AND {$this->translation_table}.timestamp = {$this->translation_log_table}.timestamp
WHERE {$this->translation_log_table}.original='$original' AND {$this->translation_log_table}.lang='$lang'
SQL;
				LogService::legacy_log( $query, 3 );
				$wpdb->query( $query );
			}

			return true;
		}

		return false;
	}

	/**
	 * Get translation alternatives for some translation.
	 *
	 * @param string $token
	 */
	function get_translation_alt( $token ) {

		//$ref = getenv('HTTP_REFERER');
		//  $original = OpenTransposh\Core\transposh_utils::base64_url_decode($token);
		$original = $token;
		LogService::legacy_log( "Inside alt for $original ($token)", 4 );

		if ( ! isset( $original ) ) {
			exit;
		}

		// Check permissions
		if ( ! ( $this->transposh->is_translator() ) ) {
			LogService::legacy_log( "Unauthorized alt request " . Utilities::get_clean_server_var( 'REMOTE_ADDR' ),
				1 );
			header( 'HTTP/1.0 401 Unauthorized alt request' );
			exit;
		}
		LogService::legacy_log( 'Passed check for editable and translator', 4 );

		// The original content is encoded as base64 before it is sent (i.e. token), after we
		// decode it should just the same after it was parsed.
		// TODO - check if needed later
		$original = esc_sql( html_entity_decode( $original, ENT_NOQUOTES, 'UTF-8' ) );

		// add our own custom header - so we will know that we got here
		// TODO - move to ajax file?
		header( 'Open Transposh: v-' . TRANSPOSH_PLUGIN_VER . ' db_version-' . DB_VERSION );

		$query = "SELECT translated, lang " .
		         "FROM {$this->translation_table} " .
		         "WHERE original='$original' AND source=0 " .
		         "ORDER BY lang";
		LogService::legacy_log( "query is $query" );
		$rows = $GLOBALS['wpdb']->get_results( $query );

		echo json_encode( $rows );
		exit;
	}

	/**
	 * Function to return human translations history
	 *
	 * @param string $date - either null for all or a date to get terms after
	 *
	 * @return array List of rows
	 */
	function get_all_human_translation_history( $date = "null", $limit = "" ) {
		$limitterm = '';
		$dateterm  = '';
		if ( $date != "null" ) {
			$dateterm = "and UNIX_TIMESTAMP(timestamp) > $date";
		}
		if ( $limit ) {
			$limitterm = "LIMIT $limit";
		}
		$query = "SELECT original, lang, translated, translated_by, UNIX_TIMESTAMP(timestamp) as timestamp " .
		         "FROM {$this->translation_log_table} " .
		         "WHERE source= 0 $dateterm " .
		         "UNION " .
		         "SELECT original, lang, translated, translated_by, UNIX_TIMESTAMP(timestamp) as timestamp " .
		         "FROM {$this->translation_table} " .
		         "WHERE source= 0 $dateterm " .
		         "ORDER BY timestamp ASC $limitterm";
		LogService::legacy_log( "query is $query" );

		return $GLOBALS['wpdb']->get_results( $query );
	}

	/**
	 *
	 * @param type $source
	 * @param type $date
	 * @param type $limit
	 * @param type $orderby
	 * @param type $order
	 * @param type $filter
	 *
	 * @return type
	 */
	function get_filtered_translations( $source = '0', $date = 'null', $limit = '', $orderby = 'timestamp', $order = 'DESC', $filter = '' ) {
		$limitterm  = '';
		$dateterm   = '';
		$sourceterm = '';
		if ( $source != '' ) {
			$sourceterm = "source=$source";
		}
		if ( $date != "null" ) {
			$dateterm = "";
			if ( $sourceterm ) {
				$dateterm = "AND ";
			}
			$dateterm .= "UNIX_TIMESTAMP(timestamp) > $date";
		}
		if ( ( $sourceterm || $dateterm ) && $filter ) {
			$filter = "AND " . $filter;
		}
		if ( $limit ) {
			$limitterm = "LIMIT $limit";
		}
		$query = "SELECT * " . //original, lang, translated, translated_by, UNIX_TIMESTAMP(timestamp) as timestamp " .
		         "FROM {$this->translation_table} ";
		if ( ( $sourceterm || $dateterm || $filter ) ) {
			$query .= "WHERE $sourceterm $dateterm $filter ";
		}
		$query .= "ORDER BY $orderby $order $limitterm";
		LogService::legacy_log( "query is $query" );

		return $GLOBALS['wpdb']->get_results( $query, ARRAY_A );
	}

	/**
	 *
	 * @param type $source
	 * @param type $date
	 * @param type $limit
	 * @param type $by
	 * @param type $order
	 * @param type $filter
	 *
	 * @return type
	 */
	function get_filtered_translations_count( $source = '0', $date = 'null', $filter = '' ) {
		$dateterm   = '';
		$sourceterm = '';
		if ( $source != '' ) {
			$sourceterm = "source=$source";
		}
		if ( $date != "null" ) {
			$dateterm = "";
			if ( $sourceterm ) {
				$dateterm = "AND ";
			}
			$dateterm .= "UNIX_TIMESTAMP(timestamp) > $date";
		}
		if ( ( $sourceterm || $dateterm ) && $filter ) {
			$filter = "AND " . $filter;
		}
		$query = "SELECT count(*) " . //original, lang, translated, translated_by, UNIX_TIMESTAMP(timestamp) as timestamp " .
		         "FROM {$this->translation_table} ";
		if ( ( $sourceterm || $dateterm || $filter ) ) {
			$query .= "WHERE $sourceterm $dateterm $filter";
		}
		LogService::legacy_log( "query is $query" );

		return $GLOBALS['wpdb']->get_var( $query );
	}

	/*
	 * Setup the translation database.
	 */

	function setup_db( $force = false ) {
		LogService::legacy_log( "Enter" );
		LogService::legacy_log( "charset: " . $GLOBALS['wpdb']->charset );
		require_once( ABSPATH . 'wp-admin/includes/upgrade.php' );

		$installed_ver = get_option( TRANSPOSH_DB_VERSION );

		if ( $installed_ver != DB_VERSION || $force ) {
			$timestamp = get_option( self::TRANSPOSH_OPTIONS_DBSETUP, 0 );
			if ( time() - 7200 > $timestamp ) { //two hours are more than enough
				delete_option( self::TRANSPOSH_OPTIONS_DBSETUP );
			} else {
				LogService::legacy_log( "we don't want to upgrade transposh tables more than once" );

				return;
			}
			update_option( self::TRANSPOSH_OPTIONS_DBSETUP, time() );

			LogService::legacy_log( "Attempting to create table {$this->translation_table}", 1 );
			// notice - keep every field on a new line or dbdelta fails
			$rows = $GLOBALS['wpdb']->get_results( "SHOW INDEX FROM {$this->translation_table} WHERE key_name = 'PRIMARY'" );
			if ( count( $rows ) ) {
				$GLOBALS['wpdb']->query( "ALTER TABLE {$this->translation_table} DROP PRIMARY KEY" );
			}
			$sql = "CREATE TABLE {$this->translation_table} (
                    original TEXT NOT NULL, 
                    lang CHAR(5) NOT NULL, 
                    translated TEXT, 
                    translated_by VARCHAR(45), 
                    source TINYINT NOT NULL, 
                    timestamp TIMESTAMP, 
                    KEY original (original(6),lang)
                    ) DEFAULT CHARSET=utf8 COLLATE=utf8_bin";

			dbDelta( $sql );
			if ( $GLOBALS['wpdb']->charset === 'utf8mb4' ) {
				LogService::legacy_log( "charset is utfmb4: " . $GLOBALS['wpdb']->charset );
				$GLOBALS['wpdb']->query( "ALTER TABLE {$this->translation_table} CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_bin" );
			} else {
				LogService::legacy_log( "charset is not utfmb4: " . $GLOBALS['wpdb']->charset );
				$GLOBALS['wpdb']->query( "ALTER TABLE {$this->translation_table} CONVERT TO CHARSET utf8 COLLATE utf8_bin" );
			}
			LogService::legacy_log( "Attempting to create table {$this->translation_log_table}", 1 );
			// notice - keep every field on a new line or dbdelta fails
			// this should be removed in a far future...
			$rows = $GLOBALS['wpdb']->get_results( "SHOW INDEX FROM {$this->translation_log_table} WHERE key_name = 'PRIMARY'" );
			if ( count( $rows ) ) {
				$GLOBALS['wpdb']->query( "ALTER TABLE {$this->translation_log_table} DROP PRIMARY KEY" );
			}
			$sql = "CREATE TABLE {$this->translation_log_table} (
                    original text NOT NULL, 
                    lang CHAR(5) NOT NULL, 
                    translated text, 
                    translated_by VARCHAR(45), 
                    source TINYINT NOT NULL, 
                    timestamp TIMESTAMP, 
                    KEY original (original(6),lang,timestamp)
                    ) DEFAULT CHARSET=utf8 COLLATE=utf8_bin";

			dbDelta( $sql );
			if ( $GLOBALS['wpdb']->charset === 'utf8mb4' ) {
				$GLOBALS['wpdb']->query( "ALTER TABLE {$this->translation_log_table} CONVERT TO CHARSET utf8mb4 COLLATE utf8mb4_bin" );
			} else {
				$GLOBALS['wpdb']->query( "ALTER TABLE {$this->translation_log_table} CONVERT TO CHARSET utf8 COLLATE utf8_bin" );
			}
			// do the cleanups too
			LogService::legacy_log( "premaint" );
			$this->db_maint();
			LogService::legacy_log( "postmaint" );
			update_option( TRANSPOSH_DB_VERSION, DB_VERSION );
			delete_option( self::TRANSPOSH_OPTIONS_DBSETUP );
		}
		LogService::legacy_log( "Exit" );
	}

	/**
	 * Provides some stats about our database
	 */
	function db_stats() {
		echo '<h4>' . __( 'Database stats', TRANSPOSH_TEXT_DOMAIN ) . '</h4>';
		$query = "SELECT count(*) as count FROM `{$this->translation_table}`";
		$rows  = $GLOBALS['wpdb']->get_results( $query );
		foreach ( $rows as $row ) {
			if ( $row->count ) {
				printf( '<p>' . __( 'Total of <strong style="color:red">%s</strong> translated phrases.', TRANSPOSH_TEXT_DOMAIN ) . '</p>', $row->count );
			}
		}

		$query = "SELECT count(*) as count,lang FROM `{$this->translation_table}` WHERE source='0' GROUP BY `lang` ORDER BY `count` DESC LIMIT 3";
		$rows  = $GLOBALS['wpdb']->get_results( $query );
		foreach ( $rows as $row ) {
			if ( $row->count ) {
				printf( '<p>' . __( '<strong>%1s</strong> has <strong style="color:red">%2s</strong> human translated phrases.', TRANSPOSH_TEXT_DOMAIN ) . '</p>', $row->lang, $row->count );
			}
		}

		echo '<h4>' . __( 'Recent activity', TRANSPOSH_TEXT_DOMAIN ) . '</h4>';
		$query = "SELECT * FROM `{$this->translation_table}` WHERE source='0' ORDER BY `timestamp` DESC LIMIT 3";
		$rows  = $GLOBALS['wpdb']->get_results( $query );
		foreach ( $rows as $row ) {
			$td = mysql2date( get_option( 'date_format' ) . ' ' . get_option( 'time_format' ), $row->timestamp );
			printf( '<p>' . __( 'On <strong>%1s</strong><br/>user <strong>%2s</strong> translated<br/>"<strong>%3s</strong>"<br/>to <strong style="color:red">%4s</strong><br/>"<strong>%5s</strong>"', TRANSPOSH_TEXT_DOMAIN ) . '</p>', $td, $row->translated_by, esc_html( $row->original ), $row->lang, esc_html( $row->translated ) );
		}
	}

	/**
	 * This function returns a list of candidate phrases which might contain a requested translated string
	 *
	 * @param string $term The search term
	 * @param string $language The language being searched
	 *
	 * @return array Original phrases in which $term appears
	 */
	function get_orignal_phrases_for_search_term( $term, $language ) {
		$n        = '%';
		$term     = esc_sql( html_entity_decode( $term, ENT_NOQUOTES, 'UTF-8' ) );
		$language = esc_sql( $language );
		$query    = "SELECT original" .
		            " FROM `{$this->translation_table}`" .
		            " WHERE `lang` = '$language'" .
		            " AND `translated` LIKE '{$n}{$term}{$n}'";
		//TODO wait for feedbacks to see if we should put a limit here.
		LogService::legacy_log( $query, 4 );
		$result = array();
		if ( strlen( $term ) < 3 ) {
			return $result;
		}
		$rows = $GLOBALS['wpdb']->get_results( $query );

		foreach ( $rows as $row ) {
			$addme = true;
			// now lets use the a-priori for reduction
			// two possibilities for reduction, new is included in old, or some old includes this new
			foreach ( $result as $k => $r ) {
				// if our original is included in a string in the result, that is no longer needed...
				if ( stripos( $r, $row->original ) !== false ) {
					unset( $result[ $k ] );
				}
				// if the other way around is true, we won't have to add it
				if ( stripos( $row->original, $r ) !== false ) {
					$addme = false;
				}
			}
			if ( $addme ) {
				$result[] = $row->original;
			}
		}

		return $result;
	}

	/**
	 * This function removes translations and translation logs from the database, only
	 * when the last translation is automated
	 *
	 * @param int $days
	 */
	function cleanup( $days = 0 ) {
		$days = (int) $days; // some security
		if ( $days == 999 ) {
			$cleanup = 'DELETE ' .
			           ' FROM ' . $this->translation_table .
			           ' WHERE original = translated' .
			           ' AND source >0';
			$result  = $GLOBALS['wpdb']->query( $cleanup );
			LogService::legacy_log( $cleanup, 4 );
			$cleanup = 'DELETE ' .
			           ' FROM ' . $this->translation_log_table .
			           ' WHERE original = translated' .
			           ' AND source >0';
			$result  = $GLOBALS['wpdb']->query( $cleanup );
			LogService::legacy_log( $cleanup, 4 );
		} else {
			$cleanup = 'DELETE ' . $this->translation_table . ' ,' . $this->translation_log_table .
			           ' FROM ' . $this->translation_table .
			           ' LEFT JOIN ' . $this->translation_log_table .
			           ' ON ' . $this->translation_table . '.original = ' . $this->translation_log_table . '.original' .
			           ' AND ' . $this->translation_table . '.lang = ' . $this->translation_log_table . '.lang' .
			           ' WHERE ' . $this->translation_table . '.source > 0' .
			           ' AND ' . $this->translation_table . ".timestamp < SUBDATE(NOW(),$days)";
			$result  = $GLOBALS['wpdb']->query( $cleanup );
			LogService::legacy_log( $cleanup, 4 );
		}
		// clean up cache so that results will actually show
		$this->cache_clean();
		exit;
	}

	function deduplicate_auto() {
		// clean duplication in the translation table (don't know how it ever happened...)
		$deduptargets = [ 'original', 'translated' ];
		foreach ( $deduptargets as $target ) {
			$dedup = 'SELECT * , count( * )' .
			         ' FROM ' . $this->translation_table .
			         ' WHERE source > 0 ' .
			         ' GROUP BY `' . $target . '` , `lang`' .
			         ' HAVING count( * ) >1';
			LogService::legacy_log( $dedup, 3 );
			$rows = $GLOBALS['wpdb']->get_results( $dedup );
			foreach ( $rows as $row ) {
				// var_dump($row);
				$row->$target = esc_sql( $row->$target );
				$row->lang    = esc_sql( $row->lang );
				$delvalues    = "($target ='{$row->$target}' AND lang='{$row->lang}' AND source > 0)";
				$update       = "DELETE FROM " . $this->translation_table . " WHERE $delvalues";
				LogService::legacy_log( $update, 3 );
				$GLOBALS['wpdb']->query( $update );
				$this->cache_delete( $row->original, $row->lang );
			}
		}
	}

	function db_maint() {
		// clean duplicate log entries
		$dedup = 'SELECT * , count( * )' .
		         ' FROM ' . $this->translation_log_table .
		         ' GROUP BY `original` , `lang` , `translated` , `translated_by` , `timestamp` , `source`' .
		         ' HAVING count( * ) >1';
		$rows  = $GLOBALS['wpdb']->get_results( $dedup );
		LogService::legacy_log( $dedup, 3 );
		foreach ( $rows as $row ) {
			$row->original      = esc_sql( $row->original );
			$row->translated    = esc_sql( $row->translated );
			$row->translated_by = esc_sql( $row->translated_by );
			$delvalues          = "(original ='{$row->original}' AND lang='{$row->lang}' AND translated='{$row->translated}'" .
			                      " AND translated_by='{$row->translated_by}' AND timestamp='{$row->timestamp}' AND source='{$row->source}')";
			$update             = "DELETE FROM " . $this->translation_log_table . " WHERE $delvalues";
			LogService::legacy_log( $update, 3 );
			$result = $GLOBALS['wpdb']->query( $update );
			$values = "('{$row->original}','{$row->lang}','{$row->translated}','$row->translated_by','$row->timestamp','$row->source')";
			$update = "INSERT INTO " . $this->translation_log_table . " (original, lang, translated, translated_by, timestamp, source) VALUES $values";
			LogService::legacy_log( $update, 3 );
			$result = $GLOBALS['wpdb']->query( $update );
			$this->cache_delete( $row->original, $row->lang );
		}

		// clean autotranslate entries post dating a human translation
		$autojunk = 'SELECT w2 . *' .
		            ' FROM ' . $this->translation_log_table . ' AS w1' .
		            ' INNER JOIN ' . $this->translation_log_table . ' AS w2' .
		            ' ON w1.original = w2.original' .
		            ' AND w1.lang = w2.lang' .
		            ' AND w1.source =0' .
		            ' AND w2.source >0' .
		            ' AND w1.timestamp < w2.timestamp';
		$rows     = $GLOBALS['wpdb']->get_results( $autojunk );
		LogService::legacy_log( $autojunk, 3 );
		foreach ( $rows as $row ) {
			$row->original      = esc_sql( $row->original );
			$row->translated    = esc_sql( $row->translated );
			$row->translated_by = esc_sql( $row->translated_by );
			$delvalues          = "(original ='{$row->original}' AND lang='{$row->lang}' AND translated='{$row->translated}'" .
			                      " AND translated_by='{$row->translated_by}' AND timestamp='{$row->timestamp}' AND source='{$row->source}')";
			$update             = "DELETE FROM " . $this->translation_log_table . " WHERE $delvalues";
			LogService::legacy_log( $update, 3 );
			$result = $GLOBALS['wpdb']->query( $update );
			$this->cache_delete( $row->original, $row->lang );
		}

		// clean duplication in the translation table (don't know how it ever happened...)
		$dedup = 'SELECT * , count( * )' .
		         ' FROM ' . $this->translation_table .
		         ' GROUP BY `original` , `lang`' .
		         ' HAVING count( * ) >1';
		LogService::legacy_log( $dedup, 3 );
		$rows = $GLOBALS['wpdb']->get_results( $dedup );
		foreach ( $rows as $row ) {
			$row->original = esc_sql( $row->original );
			$row->lang     = esc_sql( $row->lang );
			[ $source, $translation ] = $this->fetch_translation( $row->original, $row->lang );
			if ( $source != null ) {
				$delvalues = "(original ='{$row->original}' AND lang='{$row->lang}')";
				$update    = "DELETE FROM " . $this->translation_table . " WHERE $delvalues";
				LogService::legacy_log( $update, 3 );
				$result          = $GLOBALS['wpdb']->query( $update );
				$row->translated = esc_sql( $translation );
				$row->source     = esc_sql( $source );
				$values          = "('{$row->original}','{$row->lang}','{$row->translated}','{$row->translated_by}','$row->source')";
				$update          = "INSERT INTO " . $this->translation_table . " (original, lang, translated, translated_by, source) VALUES $values";
				LogService::legacy_log( $update, 3 );
				$result = $GLOBALS['wpdb']->query( $update );
			}
			$this->cache_delete( $row->original, $row->lang );
		}

		// do a major log cleanup by query for NULL sourced items
		$denullsql = "UPDATE {$this->translation_table} LEFT JOIN {$this->translation_log_table} ON " .
		             "{$this->translation_table}.original = {$this->translation_log_table}.original AND " .
		             "{$this->translation_table}.lang = {$this->translation_log_table}.lang AND " .
		             "{$this->translation_table}.translated = {$this->translation_log_table}.translated AND " .
		             "{$this->translation_table}.source = {$this->translation_log_table}.source " .
		             "SET {$this->translation_table}.translated_by = {$this->translation_log_table}.translated_by, " .
		             "{$this->translation_table}.timestamp = {$this->translation_log_table}.timestamp " .
		             "WHERE {$this->translation_table}.translated_by IS NULL";
		LogService::legacy_log( $denullsql, 3 );
		$GLOBALS['wpdb']->query( $denullsql );

		// and now the translation log is trimmed
		$removetranslogextras = "DELETE {$this->translation_log_table} FROM {$this->translation_log_table} INNER JOIN {$this->translation_table} ON " .
		                        "{$this->translation_table}.original = {$this->translation_log_table}.original AND " .
		                        "{$this->translation_table}.lang = {$this->translation_log_table}.lang AND " .
		                        "{$this->translation_table}.translated = {$this->translation_log_table}.translated AND " .
		                        "{$this->translation_table}.source = {$this->translation_log_table}.source AND " .
		                        "{$this->translation_table}.translated_by = {$this->translation_log_table}.translated_by AND " .
		                        "{$this->translation_table}.timestamp = {$this->translation_log_table}.timestamp";
		LogService::legacy_log( $removetranslogextras, 3 );
		$GLOBALS['wpdb']->query( $removetranslogextras );

		// some more cleanups
		$removebase64baddies = "DELETE FROM {$this->translation_table} WHERE `original` LIKE '%,' AND `source` != 0";
		LogService::legacy_log( $removebase64baddies, 3 );
		$GLOBALS['wpdb']->query( $removebase64baddies );

		$removetranslationsofnothing = "DELETE FROM {$this->translation_table} WHERE `original` = '' AND `source` != 0";
		LogService::legacy_log( $removetranslationsofnothing, 3 );
		$GLOBALS['wpdb']->query( $removetranslationsofnothing );

		// optimize it
		$optimizesql = "OPTIMIZE TABLE {$this->translation_table}, {$this->translation_log_table}";
		LogService::legacy_log( $optimizesql, 3 );
		$GLOBALS['wpdb']->query( $optimizesql );

		$this->cache_clean();
		//   exit;
	}

	function restore_translation( $original, $lang, $translation, $by, $timestamp ) {
		// TODO in future
		// if there is a newer human translation, just ignore this
		// if there is a newer auto translation, remove it
		// update it
		// TODO - change this part to use the update_translation function
		$original    = esc_sql( html_entity_decode( $original, ENT_NOQUOTES, 'UTF-8' ) );
		$translation = esc_sql( html_entity_decode( $translation, ENT_NOQUOTES, 'UTF-8' ) );
		$source      = 0;
		// for now - just update it...
		$values    .= "('" . $original . "','" . $translation . "','" . $lang . "','" . $source . "')";
		$delvalues .= "(original ='$original' AND lang='$lang')";
		// Setting the transaction log records
		$logvalues .= "('" . $original . "','" . $translation . "','" . $lang . "','" . $by . "',FROM_UNIXTIME(" . $timestamp . "),'" . $source . "')";

		$update = "DELETE FROM " . $this->translation_table . " WHERE $delvalues";
		LogService::legacy_log( $update, 3 );
		$result = $GLOBALS['wpdb']->query( $update );
		$update = "INSERT INTO " . $this->translation_table . " (original, translated, lang, source) VALUES $values";
		LogService::legacy_log( $update, 3 );
		$result = $GLOBALS['wpdb']->query( $update );

		if ( $result !== false ) {
			// update the transaction log too
			$log = "INSERT INTO " . $this->translation_log_table . " (original, translated, lang, translated_by, timestamp, source) " .
			       "VALUES $logvalues";
			LogService::legacy_log( $log, 3 );
			$result = $GLOBALS['wpdb']->query( $log );
		} else {
			LogService::legacy_log( $GLOBALS['wpdb']->last_error, 1 );
			LogService::legacy_log( "Error !!! failed to insert to db $original , $translation, $lang,", 0 );
			header( "HTTP/1.0 404 Failed to update language database " . $GLOBALS['wpdb']->last_error );
		}
	}

}
