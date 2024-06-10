<?php
/**
 * Refactor files get included into bin/refactor.php which passes $refactor object
 */
$refactor->setVerbose(true);
$refactor->outputMessage('e', 'WARNING: rules added to private/share/packagist/vukbgit/simplex/src/Erp/sass/ERP.css which is by default included into private/local/simplex/Backend/sass/backend.scss, so it is probably necessary to re-compile this file;');
