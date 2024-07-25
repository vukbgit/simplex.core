<?php
declare(strict_types=1);
//PHP-DI functions
use function DI\create;
use function DI\get;
//MINIMAL FUNCTIONALITIES
use Psr\Container\ContainerInterface;
use Simplex\FastRouteMiddleware;
//Zuse Zend\Diactoros\Response;
use Laminas\Diactoros\Response;
use Middlewares\RequestHandler;
use Middleland\Dispatcher;
use HttpSoft\Emitter\SapiEmitter;
//ADDITIONAL FUNCTIONALITIES
//template engine
use Twig\Loader\FilesystemLoader;
//use Twig\Environment;
use \Simplex\TwigExtended;
use Simplex\VanillaCookieExtended;
//query builder
use \Simplex\PixieConnectionExtended;
use \Simplex\PixieExtended;
//authentication
use \Simplex\Authentication;
//translations
use Simplex\TranslationsExtractor;
//refactor
use Simplex\Refactor;
//spreadsheets read/write
use Simplex\SpreadsheetReaderWriter;
//mailer
use Simplex\PHPMailerExtended;
//password generator
use Hackzilla\PasswordGenerator\Generator\ComputerPasswordGenerator;
//slugifier
use Cocur\Slugify\Slugify;
//sitemap
use Simplex\Sitemap;
//libretranslate
use Jefs42\LibreTranslate;
//to get LOCAL CLASSES
use function Simplex\mergeArrayFromFiles;
//definitions array
return array_merge(
    [
        /********************************
        * START MINIMAL FUNCTIONALITIES *
        ********************************/
        //DI container
        'DIContainer' => function(ContainerInterface $c) {
            return $c;
        },
        //router
        'simplexFastRouteMiddleware' => create(FastRouteMiddleware::class)
            ->constructor(ENVIRONMENT, get('routes'), TMP_DIR),
        'routes' => function() {
            //search routes definitions into local namespace
            return mergeArrayFromFiles(PRIVATE_LOCAL_DIR, 'routes.php');
        },
        //request handler middleware
        'requestHandler' => create(RequestHandler::class)
            ->constructor(get('DIContainer')),
        //dispatcher
        'middlevareQueue' => function(ContainerInterface $DIContainer) {
            return require MIDDLEWARE_QUEUE_PATH;
        },
        'dispatcher' => create(Dispatcher::class)
            ->constructor(get('middlevareQueue'), get('DIContainer')),
        //http response
        'response' => create(response::class),
        //emitter
        'emitter' => create(SapiEmitter::class),
        //translations
        'translationsExtractor' => create(TranslationsExtractor::class)
            ->constructor(get('DIContainer'), get('response'), get('templateEngine'), get('cookieManager')),
        //translations
        'refactor' => create(Refactor::class)
            ->constructor(),
        /*******************************
        * STOP MINIMAL FUNCTIONALITIES *
        *******************************/
        /***********************************
        * START ADDITIONAL FUNCTIONALITIES *
        ***********************************/
        //template engine, templates paths are set into Simplex\ControllerWithTemplateAbstract::renderTemplate
        'twigFilesystemLoader' => create(FilesystemLoader::class)
            ->constructor(),
        'templateEngine' => create(TwigExtended::class)
            ->constructor(get('twigFilesystemLoader')),
        //cookie manager
        'cookieManager' => create(VanillaCookieExtended::class)
            ->constructor(),
        //query builder
        'dbDriver' => function(){
            $config = require sprintf('%s/db.php', LOCAL_CONFIG_DIR);
            if(!isset($config[ENVIRONMENT])) {
                throw new \Exception(sprintf('There is no database configuration for current environment \'%s\'', ENVIRONMENT));
            }
            return $config[ENVIRONMENT]['driver'];
        },
        'dbConfig' => function(){
            $config = require sprintf('%s/db.php', LOCAL_CONFIG_DIR);
            if(!isset($config[ENVIRONMENT])) {
                throw new \Exception(sprintf('There is no database configuration for current environment \'%s\'', ENVIRONMENT));
            }
            return $config[ENVIRONMENT];
        },
        'pixieConnection' => create(PixieConnectionExtended::class)
            ->constructor(get('dbDriver'), get('dbConfig'), 'QB'),
        'queryBuilder' => create(PixieExtended::class)
            ->constructor(get('pixieConnection')),
        //authentication
        'simplexAuthenticationMiddleware' => create(Authentication\Middleware::class)
            ->constructor(get('DIContainer'), get('cookieManager')),
        //spreadsheet reader/write
        'spreadsheet' => create(SpreadsheetReaderWriter::class),
        //mailer
        'mailer' => create(PHPMailerExtended::class),
        //password generator
        'passwordGenerator' => create(ComputerPasswordGenerator::class),
        //slugifier
        'slugifier' => create(Slugify::class),
        //sitemap
        'xmlSitemap' => create(Sitemap\XmlSitemap::class),
        //sitemap
        'translator' => create(LibreTranslate::class)
          ->constructor(
            defined('AUTOMATIC_TRANSLATIONS') ? constant('AUTOMATIC_TRANSLATIONS')->server : null,
            defined('AUTOMATIC_TRANSLATIONS') ? constant('AUTOMATIC_TRANSLATIONS')->port : null,
            defined('AUTOMATIC_TRANSLATIONS') ? constant('AUTOMATIC_TRANSLATIONS')->defaultSourceLanguage : null,
          ),
        /**********************************
        * STOP ADDITIONAL FUNCTIONALITIES *
        **********************************/
    ],
    //search definitions into local namespace (unless local environment is "disabled", see bin/bootstrap.php)
    !DISABLE_LOCAL ? mergeArrayFromFiles(PRIVATE_LOCAL_DIR, 'di-container.php') : []
);
