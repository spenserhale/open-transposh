<?php

//Define for transposh plugin version
const TRANSPOSH_PLUGIN_VER = '2.1.7';

//Language indicator in URL. i.e. lang=en
const LANG_PARAM = 'lang';

//Edit mode indicator in URL. i.e. lang=en&edit=true
const EDIT_PARAM = 'tpedit';

//Enable in memory cache usage, APC, xcache
const TP_ENABLE_CACHE = true;
//What is the cache items TTL
const TP_CACHE_TTL = 86400;
//Constants for memcached
const TP_MEMCACHED_SRV  = '127.0.0.1';
const TP_MEMCACHED_PORT = 11211;

//Class marking a section not be translated.
const NO_TRANSLATE_CLASS        = 'no_translate';
const NO_TRANSLATE_CLASS_GOOGLE = 'notranslate';
const ONLY_THISLANGUAGE_CLASS   = 'only_thislanguage';

//Get text breakers
define( 'TP_GTXT_BRK', chr( 1 ) ); // Gettext breaker
define( 'TP_GTXT_IBRK', chr( 2 ) ); // Gettext inner breaker (around %s)
define( 'TP_GTXT_BRK_CLOSER', chr( 3 ) ); // Gettext breaker closer
define( 'TP_GTXT_IBRK_CLOSER', chr( 4 ) ); // Gettext inner breaker closer
//External services
const TRANSPOSH_BACKUP_SERVICE_URL  = 'http://svc.transposh.org/backup';
const TRANSPOSH_RESTORE_SERVICE_URL = 'http://svc.transposh.org/restore';

//Define the new capability that will be assigned to roles - translator
const TRANSLATOR = 'translator';

//Current jQuery UI
const JQUERYUI_VER = '1.12.1';

//Define segment id prefix, will be included in span tag. also used as class identifier
const SPAN_PREFIX = 'tr_';

//Our text domain
const TRANSPOSH_TEXT_DOMAIN = 'transposh';

//0.3.5 - Storing all options in this config option
const TRANSPOSH_OPTIONS = 'transposh_options';

//0.8.4 - Storing oht project
const TRANSPOSH_OPTIONS_OHT          = 'transposh_options_oht';
const TRANSPOSH_OPTIONS_OHT_PROJECTS = 'transposh_options_oht_projects';
const TRANSPOSH_OHT_DELAY            = 600;

//0.9.6 - Making sure Google works
const TRANSPOSH_OPTIONS_YANDEXPROXY = 'transposh_options_yandexproxy';
const TRANSPOSH_YANDEXPROXY_DELAY   = 3600; // give it an hour
//0.9.6 - Making sure Google works
const TRANSPOSH_OPTIONS_GOOGLEPROXY = 'transposh_options_googleproxy';
const TRANSPOSH_GOOGLEPROXY_DELAY   = 86400; // give it a day
//0.5.6 new definitions
//Defintions for directories used in the plugin
const TRANSPOSH_DIR_CSS     = 'css';
const TRANSPOSH_DIR_IMG     = 'img';
const TRANSPOSH_DIR_JS      = 'js';
const TRANSPOSH_DIR_WIDGETS = 'widgets';
const TRANSPOSH_DIR_UPLOAD  = 'transposh'; //1.0.1

const TRANSPOSH_WIDGET_PREFIX = 'tpw_';
const TR_NONCE                = "transposh_nonce";

const TRANSLATIONS_TABLE = 'translations';
const TRANSLATIONS_LOG   = 'translations_log';

//Database version
const DB_VERSION = '1.06';

//Constant used as key in options database
const TRANSPOSH_DB_VERSION = "transposh_db_version";
const TRANSPOSH_OPTIONS_DBSETUP = 'transposh_inside_dbupgrade';

const TP_FROM_POST = 'tp_post_1x';
// types of options
const TP_OPT_BOOLEAN = 0;
const TP_OPT_STRING  = 1;
const TP_OPT_IP      = 2;
const TP_OPT_OTHER   = 3;
