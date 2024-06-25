<?php
declare(strict_types=1);

namespace Simplex;

use Simplex\Erp\ControllerAbstract;
use function Simplex\loadLanguages;
use jblond\TwigTrans\Translation;

/*
 * Class to extract translations from templates
 */
class TranslationsExtractor extends ControllerAbstract
{
  /*
  * Extracts translations
  * @param string $operation
  * @param string $context: share | local
  */
  public function extractTranslations(string $operation, string $context)
  {
    switch ($operation) {
      //generate a clean pot file from both share and local
      case 'create':
        //Twig cache
        $this->generateTemplatesCaches($context);
        //generate pot file
        $this->generateStartingPotFile($context);
      break;
      //generate po files for each language to update translations
      case 'update':
        //Twig cache
        $this->generateTemplatesCaches($context);
        //generate pot file
        $this->generateStartingPotFile($context);
        //generate pot file
        $this->generateUpdatedPoFile($context);
      break;
    }
  }
  
  /**
   * Builds the path to the translations cache
   **/
  protected function buildPathToTranslationsCache()
  {
    return sprintf('%s/translations', TMP_DIR);
  }

  /**
   * Builds the path to a context template cache
   * @param string $context
   **/
  protected function buildPathToContextTemplatesCache(string $context)
  {
    return sprintf('%s/cache/%s', $this->buildPathToTranslationsCache(), $context);
  }
  
  /**
   * Generate templates cache for both share and local templates
   * @param string $context: share | local
   **/
  protected function generateTemplatesCaches(string $context)
  {
    //build helpers
    $this->buildTemplateHelpersBack();
    //set templates namespaces
    $loader = $this->template->getLoader();
    $loader->addPath(SHARE_TEMPLATES_DIR, '__main__');
    $loader->addPath(SHARE_TEMPLATES_DIR, 'share');
    $loader->addPath(LOCAL_TEMPLATES_DIR, '__main__');
    $loader->addPath(LOCAL_TEMPLATES_DIR, 'local');
    $this->template->setLoader($loader);
    //clean cache
    $pathToTemplatesCache = $this->buildPathToTranslationsCache();
    if(is_dir($pathToTemplatesCache)) {
      $command = sprintf('rm -rf %s', $pathToTemplatesCache);
      exec($command);
    }
    //generate cache
    //share cache is generated anyway
    $this->generateTemplatesCache('share', SHARE_TEMPLATES_DIR);
    //local cache is generated only in local context
    if($context == 'local') {
      $this->generateTemplatesCache('local', LOCAL_TEMPLATES_DIR);
    }
  }
  
  /**
   * Generate templates cache for both share and local templates
   * @param string $context: share | local
   * @param string $pathToTemplatesFolder
   **/
  protected function generateTemplatesCache(string $context, string $pathToTemplatesFolder)
  {
    echo 'GENERATING TEMPLATES CACHE...' . PHP_EOL;
    //build cache
    $pathToContextTemplatesCache = $this->buildPathToContextTemplatesCache($context);
    $this->template->setCache($pathToContextTemplatesCache);
    $this->template->enableAutoReload();
    #$twig->addExtension(new Twig_Extensions_Extension_I18n());
    // iterate over all your templates
    foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($pathToTemplatesFolder), \RecursiveIteratorIterator::LEAVES_ONLY) as $file) {
      // force compilation
      if ($file->isFile() && $file->getExtension() == 'twig') {
        $absolutePathToTemplate = sprintf('%s/%s', $file->getPath(), $file->getFilename());
        echo sprintf('%s%s', $absolutePathToTemplate, PHP_EOL);
        $relativePathToTemplate = str_replace($pathToTemplatesFolder . '/', '', $absolutePathToTemplate);
        $templateClass = $this->template->getTemplateClass($relativePathToTemplate);
        $template = $this->template->loadTemplate($templateClass, $relativePathToTemplate);
      }
    }
    echo PHP_EOL;
  }
  
  /**
   * Generate .pot file to start a new translation
   * @param string $context: share | local
   **/
  protected function generateStartingPotFile(string $context)
  {
    $domain = 'simplex';
    $package = 'Simplex';
    $pathToCacheFolder = $this->buildPathToTranslationsCache();
    $pathToFile = sprintf('%s/%s', $pathToCacheFolder, $domain);
    $privateLocalDir = $context == 'local' ? PRIVATE_LOCAL_DIR : '';
    echo 'GENERATING STARTING POT FILE...' . PHP_EOL;
    $command = <<<EOT
find {$this->buildPathToTranslationsCache()} {$privateLocalDir} -type f \( -name '*.php' \) -print | xargs xgettext -c --default-domain={$domain} -p {$pathToCacheFolder} --from-code=UTF-8 --no-location  -L PHP -d {$domain} --package-name={$package} - && mv {$pathToFile}.po {$pathToFile}.pot
EOT;
    passthru($command, $output);
    if($output == 0) {
      echo sprintf('POT FILE GENERATED AS %s.pot%s', $pathToFile, PHP_EOL);
    } else {
      echo sprintf('ERROR GENERATING POT FILE FOR DOMAIN %%s', $domain, PHP_EOL);
    }
  }
  
  /**
   * Generate updated .po file for each language
   * @param string $context: share | local
   **/
  protected function generateUpdatedPoFile(string $context)
  {
    //load configured languages
    $languages = loadLanguages($context);
    foreach ($languages as $languageCode => $language) {
      $this->generateUpdatedLanguagePoFile($context, $language);
    }
  }
  
  /**
   * Generate updated .po file for each language
   * update local
   * - cache totale
   * - 
   * @param string $context: share | local
   * @param object $language
   **/
  protected function generateUpdatedLanguagePoFile(string $context, object $language)
  {
    $languageIETF = sprintf('%s_%s', $language->{'ISO-639-1'}, $language->{'ISO-3166-1-2'});
    echo sprintf('GENERATING %s UPDATED PO FILE FOR %s...%s', $context, $languageIETF, PHP_EOL);
    $domain = 'simplex';
    $package = 'Simplex';
    //destination po file location changes by context
    $pathToShareLocalesRoot = sprintf('%s/../locales', PRIVATE_SHARE_DIR);
    $pathToLocalLocalesRoot = TRANSLATIONS_DIR;
    $pathToSharePoFolder = sprintf('%s/%s/LC_MESSAGES', $pathToShareLocalesRoot, $languageIETF);
    $pathToLocalPoFolder = sprintf('%s/%s/LC_MESSAGES', $pathToLocalLocalesRoot, $languageIETF);
    $pathToSharePoFile = sprintf('%s/%s.po', $pathToSharePoFolder, $domain);
    $pathToLocalPoFile = sprintf('%s/%s.po', $pathToLocalPoFolder, $domain);
    $pathToLocalMoFile = sprintf('%s/%s.mo', $pathToLocalPoFolder, $domain);
    $paths = [
      'share' => (object) [
        'localesRoot' => $pathToShareLocalesRoot,
        'poFolder' => $pathToSharePoFolder,
        'poFile' => $pathToSharePoFile
      ],
      'local' => (object) [
        'localesRoot' => $pathToLocalLocalesRoot,
        'poFolder' => $pathToLocalPoFolder,
        'poFile' => $pathToLocalPoFile
      ]
    ];
    $pathToCacheFolder = $this->buildPathToTranslationsCache();
    $pathToPotFile = sprintf('%s/%s.pot', $pathToCacheFolder, $domain);
    $privateLocalDir = $context == 'local' ? PRIVATE_LOCAL_DIR : '';
    $commands = <<<EOT
find {$this->buildPathToTranslationsCache()} {$privateLocalDir} -type f \( -name '*.php' \) -print | xargs xgettext -c --default-domain={$domain} -p {$paths[$context]->poFolder} --from-code=UTF-8 --no-location -L PHP -d {$domain} --package-name={$package} -j - && msgattrib --set-obsolete --ignore-file={$pathToPotFile} -o {$paths[$context]->poFile} {$paths[$context]->poFile}
EOT;
    //if local context cat the share po file and regenerate the mo file to incorporate any new share translation
    if($context == 'local' && is_file($pathToSharePoFile)) {
      $commands .= <<<EOT
      && msgcat --use-first -o {$pathToLocalPoFile} {$pathToSharePoFile} {$pathToLocalPoFile} && msgfmt -o {$pathToLocalMoFile} {$pathToLocalPoFile}
EOT;
    }
    //exec commands
    passthru($commands, $output);
    if($output == 0) {
      echo sprintf('PO FILE GENERATED AS %s%s', $paths[$context]->poFile, PHP_EOL);
    } else {
      echo sprintf('ERROR GENERATING POT FILE FOR LANGUAGE %%s', $languageIETF, PHP_EOL);
    }
  }
}
