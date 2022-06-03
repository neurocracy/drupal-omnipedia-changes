<?php

namespace Drupal\omnipedia_changes\Event\Omnipedia\Changes;

use Drupal\omnipedia_changes\Event\Omnipedia\Changes\AbstractDiffEvent;
use Drupal\omnipedia_core\Entity\NodeInterface;
use Symfony\Component\DomCrawler\Crawler;

/**
 * Omnipedia changes diff post-build event.
 */
class DiffPostBuildEvent extends AbstractDiffEvent {

  /**
   * A Symfony DomCrawler instance containing the diff DOM.
   *
   * @var \Symfony\Component\DomCrawler\Crawler
   */
  protected $crawler;

  /**
   * {@inheritdoc}
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   A Symfony DomCrawler instance containing the diff DOM.
   */
  public function __construct(
    NodeInterface $currentNode,
    NodeInterface $previousNode,
    string        $currentRendered,
    string        $previousRendered,
    Crawler       $crawler
  ) {

    parent::__construct(
      $currentNode, $previousNode, $currentRendered, $previousRendered
    );

    $this->setCrawler($crawler);

  }

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
