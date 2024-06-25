<?php
declare(strict_types=1);
//bootstrap
require_once __DIR__ . '/bootstrap.php';
//folder for translations po and mo files
define('TRANSLATIONS_DIR', sprintf('%s/locales', PRIVATE_LOCAL_DIR));
/*******************
* SCRIPT ARGUMENTS *
*******************/
//operation
$possibleOperations = ['create', 'update'];
$possibleContexts = ['share', 'local'];
if(!isset($argv[1]) || !isset($argv[2]) || !in_array($argv[1], $possibleOperations) || !in_array($argv[2], $possibleContexts)) {
    exit(formatMessage(
      'e',
      sprintf('script %s must be called passing "operation" as first argument with possible values %s and "context" as second argument with possible values %s%s', $argv[0], implode(' or ', $possibleOperations), implode(' or ', $possibleContexts), PHP_EOL)
    ));
} else {
    $operation = $argv[1];
}
//context
$context = $argv[2];
/********************************
* build local templates helpers *
********************************/
foreach (\Nette\Utils\Finder::findFiles('templates-helpers.php')->from(sprintf('%s/private/local/simplex', ABS_PATH_TO_ROOT)) as $file) {
    $filePath = $file->__toString();
    require $filePath;
}
/*************************
* TRANSLATIONS EXTRACTOR *
*************************/
$translationsExtractor = $DIContainer->get('translationsExtractor');
$translationsExtractor->extractTranslations($operation, $context);
