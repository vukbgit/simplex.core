<?php

namespace Simplex;

use \Nette\Utils\Finder;
use Cocur\Slugify\Slugify;

if (!function_exists('Simplex\slugToPSR1Name')) {
    /**
    * Turns a word in slug notation (route part) into a form as defined by PSR1 standard (https://www.php-fig.org/psr/psr-1/) for class names, method names and such
    * @param string $slug: term in slug form to be translated into PSR1
    * @param string $type: the type of element to translate to, so far c(lass) | m(ethod)
    *
    * @return string
    */
    function slugToPSR1Name(string $slug, string $type) : string
    {
        switch ($type) {
            case 'class':
            case 'c':
                return str_replace(' ', '', ucwords(str_replace('-', ' ', $slug)));
            break;
            case 'method':
            case 'm':
                return lcfirst(str_replace(' ', '', ucwords(str_replace('-', ' ', $slug))));
            break;
            default:
                throw new \Exception(sprintf('function Simplex\slugToPSR1Name: type parameter \'%s\' value is not handled', $type));
            break;
        }
    }
}

if (!function_exists('Simplex\PSR1NameToSlug')) {
    /**
    * Turns a word in PSR1 standard (https://www.php-fig.org/psr/psr-1/) for class names, method names and such to slug notation
    * @param string $psr1: term in PSR1 form to be translated into slug
    *
    * @return string
    */
    function PSR1NameToSlug(string $psr1) : string
    {
        $pattern = '/(?<!^)([A-Z]{1,1})/';
        return strtolower(preg_replace_callback(
            $pattern,
            function($matches) {
                return sprintf('-%s', strtolower($matches[0]));
            },
            $psr1
        ));
    }
}

if (!function_exists('Simplex\requireFromFiles')) {
    /**
    * Searches a folder for files by file name pattern and requires them
    * @param string $folder: to search recursively into
    * @param string $pattern: file name to search for, * can be used
    */
    function requireFromFiles(string $folder, string $pattern)
    {
        foreach (Finder::findFiles($pattern)->from($folder) as $file) {
            $filePath = $file->__toString();
            require $filePath;
        }
    }
}

if (!function_exists('Simplex\mergeArrayFromFiles')) {
    /**
    * Searches a folder for files by file name pattern, expectes an array returned by file inclusion, merges all returned arrays
    * @param string $folder: to search recursively into
    * @param string $pattern: file name to search for, * can be used
    *
    * @return array
    */
    function mergeArrayFromFiles(string $folder, string $pattern) : array
    {
        $return = [];
        foreach (Finder::findFiles($pattern)->from($folder) as $file) {
            $filePath = $file->__toString();
            $fileArray = require $filePath;
            if(is_array($fileArray)) {
                $return = array_merge($return, $fileArray);
            } else {
                throw new \Exception(sprintf('file "%s" MUST return an array on requirement', $filePath));
            }
        }
        return $return;
    }
}

if (!function_exists('Simplex\mergeObjects')) {
    /**
    * Merges two objects sio that properties from the second one override correspndent of the first one
    * @param object $object1
    * @param object $object2
    *
    * @return object
    */
    function mergeObjects(object $object1, object $object2) : object
    {
        return (object) array_merge((array) $object1, (array) $object2);
    }
}

if (!function_exists('Simplex\getInstanceNamespace')) {
    /**
    * Gets namespace of a class defined into Simplex\Local
    * @param mixed $instance
    * @param bool $fromWithinSimplex: whteher first part of namespace is to be truncated
    *
    * @return object
    */
    function getInstanceNamespace($instance, bool $fromWithinSimplex = false) : string
    {
        $reflection = new \ReflectionClass($instance);
        /*$namespace = $reflection->getName();
        $search = [sprintf('\%s', $reflection->getShortName())];
        if($fromWithinSimplex) {
            $search[] = 'Simplex\Local\\';
        }
        return str_replace($search, '', $namespace);*/
        $namespace = $reflection->getNamespaceName();
        if($fromWithinSimplex) {
            $search = 'Simplex\Local\\';
            $namespace = str_replace($search, '', $namespace);
        }
        return $namespace;
    }
}

if (!function_exists('Simplex\getInstancePath')) {
    /**
    * Gets namespace of a class defined into Simplex\Local
    * @param mixed $instance
    * @param bool $fromWithinSimplex: whteher first part of namespace is to be truncated
    *
    * @return object
    */
    function getInstancePath($instance) : string
    {
        $namespace = getInstanceNamespace($instance, true);
        return sprintf('%s/%s', PRIVATE_LOCAL_DIR, str_replace('\\', '/', $namespace));
    }
}

if (!function_exists('Simplex\loadLanguages')) {
    /**
    * Loads configured languages from json file
    * @param string $context: share | local, share uses the installations languages dafinition file to include always all of supported languages
    * @return object
    */
    function loadLanguages(string $context) : object
    {
        switch($context) {
            case 'share':
                $languagesConfigFilePath = sprintf('%s/../installation/private/local/simplex/config/languages.json', PRIVATE_SHARE_DIR);
            break;
            case 'local':
                $languagesConfigFilePath = sprintf('%s/languages.json', LOCAL_CONFIG_DIR);
            break;
        }
        return json_decode(file_get_contents($languagesConfigFilePath));
    }
}

if (!function_exists('Simplex\buildLocaleRoute')) {
  /**
  * Builds a route definition for router or an actual route locale aware
  * @param string $target: definition | route
  * @param object $language: as returned from a loadLanguages call
  * @param object $routeDefinitionLocale
  * @param array $multipleTokensKeys: in case some token has multiple possible values the key to be used, in the order they appear inside route definition
  * @param string $textFragment with or without hash
  * @return string
  */
  function buildLocaleRoute(string $target, object $language, object $routeDefinitionLocale, array $multipleTokensKeys = [], string $textFragment = null) :string
  {
    $routeKey = $routeDefinitionLocale->key;
    $tokensDefinitions = $routeDefinitionLocale->tokens;
    $slugifier = new Slugify();
    $languageCode = $language->{'ISO-639-1'};
    $languageIETF = sprintf('%s_%s', $languageCode, $language->{'ISO-3166-1-2'});
    //putenv(sprintf('LC_ALL=%s', $languageIETF));
    setlocale(LC_ALL, sprintf('%s.utf8', $languageIETF));
    $domain = 'simplex';
    // Specify the location of the translation tables
    bindtextdomain($domain, sprintf('%s/locales', PRIVATE_LOCAL_DIR));
    bind_textdomain_codeset($domain, 'UTF-8');
    textdomain($domain);
    $routeTokens = [];
    $tokenIndex = 0;
    $multipleTokensIndex = -1;
    foreach($tokensDefinitions as $tokenDefinition) {
      //language code
      if($tokenDefinition == '__lang') {
        switch ($target) {
          case 'definition':
            $routeTokens[] = sprintf('{lang:%s}', $languageCode);
          break;
          case 'route':
            $routeTokens[] = $languageCode;
          break;
        }
      } elseif(is_string($tokenDefinition)) {
      //fixed value
        switch ($target) {
          case 'definition':
            $routeTokens[] = $tokenDefinition;
          break;
          case 'route':
            if(empty($multipleTokensKeys)) {
              $routeTokens[] = $tokenDefinition;
            } else {
              $routeTokens[] = $multipleTokensKeys[$tokenIndex][$languageCode];
            }
          break;
        }
      } elseif(is_object($tokenDefinition)) {
        //if no values property for alternatives, use the key
        if(isset($tokenDefinition->values)) {
          $keysToBeTranslated = $tokenDefinition->values;
          $multipleTokensIndex++;
        } else {
          $keysToBeTranslated = [$tokenDefinition->key];
        }
        $translatedSlugs = [];
        switch ($tokenDefinition->source) {
          case 'gettext':
            foreach($keysToBeTranslated as $keyToBeTranslated) {
              $translatedLabel = gettext($keyToBeTranslated);
              $sluggedLabel = $slugifier->slugify($translatedLabel);
              $translatedSlugs[$keyToBeTranslated] = $sluggedLabel;
            }
          break;
          case 'db':
            /*value is an array of records, each contains properties:
            * mixed 'id' unique record identifier (i.e. int table autoincrement field)
            * array 'slug' indexed by language code
            */
            foreach($tokenDefinition->values as $record) {
              $slug = $record->slug[$languageCode];
              $translatedSlugs[sprintf('%s-%s', $routeKey, $record->id)] = $slug;
            }
          break;
        }
        switch ($target) {
          case 'definition':
            $routeTokens[] = sprintf('{%s:%s}', $tokenDefinition->key, implode('|', array_values($translatedSlugs)));
          break;
          case 'route':
            if(!isset($tokenDefinition->values) || empty($multipleTokensKeys)) {
              $routeTokens[] = reset($translatedSlugs);
            } else {
              $routeTokens[] = $translatedSlugs[$multipleTokensKeys[$multipleTokensIndex]];
            }
          break;
        }
      }
      $tokenIndex++;
    }
    return sprintf(
      '/%s%s',
      implode('/', $routeTokens),
      $textFragment ? (!str_starts_with($textFragment, '#') ? '#' : '') . $textFragment : ''
    );
  }
}
  /**
  * Builds routes definition for router
  * @param object $languages as returned from a loadLanguages call
  * @param array $routesDefinitions
  * @return string
  */
if (!function_exists('Simplex\buildLocaleRoutes')) {
  function buildLocaleRoutes(object $languages, array $routesDefinitions) : array
  {
    $routes = [];
    foreach($routesDefinitions as $routeDefinition) {
      //locale route
      if(isset($routeDefinition['handler'][1]['locale'])) {
        $locale = $routeDefinition['handler'][1]['locale'];
        if(!isset($locale->key)) {
          throw new \Exception(sprintf('Missing key for locale route definition in %s', __FILE__));      
        }
        if(!isset($locale->tokens)) {
          throw new \Exception(sprintf('Missing tokens for locale route definition in %s', __FILE__));      
        }
        //loop languages
        foreach($languages as $language) {
          $routeDefinition['key'] = sprintf('%s_%s', $locale->key, $language->{'ISO-639-1'});
          $routeDefinition['route'] = buildLocaleRoute('definition', $language, $locale);
          $routes[] = $routeDefinition;
        }
      } else {
        $routes[] = $routeDefinition;
      }
    }
    return $routes;
  }
}
