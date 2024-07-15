<?php
declare(strict_types=1);

namespace Simplex\Sitemap;

/**
 * Abstract Class to be extended with concrete classes for formats described into https://www.sitemaps.org/protocol.html
 * @link https://www.sitemaps.org/protocol.html
 * @todo so far only xml format implemented
 */
abstract class Sitemap
{
  /**
   * Format
   * @var string $format: xml|feed|text
   */
  protected $format;

  /**
   * Constructor.
   * @param string $format: xml|feed|text
   */
  public function __construct(string $format)
  {
    $this->format = $format;
  }
}
