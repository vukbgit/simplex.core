<?php
/********
* LOCAL *
********/
//included into exceptions message
define('TECH_EMAIL', '');
//used to set page title tag in some templates
define('BRAND', '');
//characters to be used for emails obfuscation
define("MAIL_AT_REPLACEMENT","xxx");
define("MAIL_DOT_REPLACEMENT","§§§");
/******************
* WEB ENVIRONMENT *
******************/
define('HOST', $_SERVER['HTTP_HOST'] ?? null);
define('MIDDLEWARE_QUEUE_PATH', sprintf('%s/middleware.php', SHARE_CONFIG_DIR));
//folder with HTTP errors pages
define('ERROR_DIR', sprintf('%s/errors', PRIVATE_SHARE_DIR));
//to force router cache to enable/disable, by default is disabled in development ENVIRONMENT and enabled in production ENVIRONMENT
//define('ROUTER_CACHE_ENABLED', false);
/**********
* SESSION *
**********/
//session.cookie_path (defaults to /), useful to host multiple applications with distinct backend under the same domain
//in this case backend routes must begin with SESSION_COOKIE_PATH
define('SESSION_COOKIE_PATH', null);
if(php_sapi_name() != 'cli') {    
    if(!isset($_SERVER['HTTPS'])) {
        echo 'It is not possible to check HTTPS by $_SERVER[\'HTTPS\'] and therefore it is not possible to set SESSION_COOKIE_SECURE constant';
        exit;
    } else {
        $sessionCookieSecure = $_SERVER['HTTPS'] == 'on';
    }
} else {
    $sessionCookieSecure = false;
}
define('SESSION_COOKIE_SECURE', $sessionCookieSecure);
/**********
* COOKIES *
**********/
//cookies default duration  in minutes (525600 minutes = 1 year)
define('COOKIE_DURATION', 525600 * 5);
/******
* ERP *
******/
//whether not to store tables filters values into subjects cookies 
define('FORGET_ALL_FILTERS', false);
//for decimal fields validation
//, AS THOUSAND SEPARATOR AND . AS DECIMAL SEPARATOR
define('FLOAT_REGEX', '^(?:[0-9]{0,3}\.?)?[0-9]{1,3}(?:,[0-9]{1,2})?$');
//. AS THOUSAND SEPARATOR AND , AS DECIMAL SEPARATOR
//define('FLOAT_REGEX', '^(?:[0-9]{0,3},?)?[0-9]{1,3}(?:\.[0-9]{1,2})?$');
//for UUID primary keys validation
define('UUID_REGEXP_CORE', '[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}');
define('UUID_REGEXP', sprintf('^%s$', UUID_REGEXP_CORE));
/***************
* TRANSLATIONS *
***************/
//folder for translations po and mo files
define('TRANSLATIONS_DIR', sprintf('%s/locales', PRIVATE_LOCAL_DIR));
