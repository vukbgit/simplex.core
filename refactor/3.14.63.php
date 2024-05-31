<?php
/**
 * Refactor files get included into bin/refactor.php which passes $refactor object
 */
$refactor->setVerbose(true);
$refactor->outputMessage('e', 'WARNING: pageFooter block has been added to ERP frame.twig with a parent() function call inside, it is necessary to add the block into any ERP area (i.e. Backend) template');
