<?php
/**
 * Refactor files get included into bin/refactor.php which passes $refactor object
 */
$refactor->setVerbose(true);
//markdown
$refactor->searchPatternInLinesReplace(
  '{% markdown %}',
  $folders = ['private/local'],
  $exclude = [],
  $filesNames = ['*.twig'],
  '{% apply markdown_to_html %}'
);
$refactor->searchPatternInLinesReplace(
  '{% endmarkdown %}',
  $folders = ['private/local'],
  $exclude = [],
  $filesNames = ['*.twig'],
  '{% endapply %}'
);