<?php
declare(strict_types=1);

namespace Simplex\Sitemap;

use Thepixeldeveloper\Sitemap\Urlset;
use Thepixeldeveloper\Sitemap\Url;
use Thepixeldeveloper\Sitemap\SitemapIndex;
use Thepixeldeveloper\Sitemap\Sitemap as ShareSitemap;
use Thepixeldeveloper\Sitemap\Drivers\XmlWriterDriver;

/**
 * Abstract Class to be extended with concrete classes for formats described into https://www.sitemaps.org/protocol.html
 * @link https://www.sitemaps.org/protocol.html
 * @todo so far only xml format implemented
 */
class XmlSitemap extends Sitemap
{
  /**
   * Constructor.
   * @param string $format: xml|feed|text
   */
  public function __construct()
  {
    parent::__construct('xml');
  }

  /**
   * Builds an index
   * @param array $sitemapUrls
   */
  public function index(array $sitemapUrls)
  {
    $index = new SitemapIndex();
    foreach ($sitemapUrls as $sitemapUrl) {
      $index->add(new ShareSitemap($sitemapUrl));
    }
    return $index;
  }


  /**
   * Builds an urlset
   * @param array $sitemapUrls
   */
  public function urlset(array $sitemapUrls)
  {
    $urlset = new Urlset();
    foreach ($sitemapUrls as $sitemapUrl) {
      $urlset->add(new Url($sitemapUrl));
    }
    return $urlset;
  }

  /**
   * Builds output content to be used by Controller::Output along with a Content-type of 'text/xml'
   * @param array $urlset
   * @param string $xslPath
   **/
  public function buildOutput(\Thepixeldeveloper\Sitemap\Collection $urlset, string $xslPath = null)
  {
    $driver = new XmlWriterDriver();
    if($xslPath) {
      $driver->addProcessingInstructions('xml-stylesheet', sprintf('type="text/xsl" href="%s"', $xslPath));
    }
    $urlset->accept($driver);
    return $driver->output();
  }
}