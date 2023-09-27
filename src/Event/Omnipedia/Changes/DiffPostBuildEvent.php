<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Event\Omnipedia\Changes;

use Drupal\node\NodeInterface;
use Drupal\omnipedia_changes\Event\Omnipedia\Changes\AbstractDiffEvent;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Omnipedia changes diff post-build event.
 */
class DiffPostBuildEvent extends AbstractDiffEvent {

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   A Symfony DomCrawler instance containing the diff DOM.
   */
  public function __construct(
    protected NodeInterface $currentNode,
    protected NodeInterface $previousNode,
    protected string        $currentRendered,
    protected string        $previousRendered,
    protected Crawler       $crawler,
  ) {}

  /**
   * Get the Symfony DomCrawler instance.
   *
   * @return \Symfony\Component\DomCrawler\Crawler
   *   A Symfony DomCrawler instance containing the diff DOM.
   */
  public function getCrawler() {
    return $this->crawler;
  }

  /**
   * Set the Symfony DomCrawler instance.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   A Symfony DomCrawler instance containing the diff DOM.
   */
  public function setCrawler(Crawler $crawler) {
    $this->crawler = $crawler;
  }

}
