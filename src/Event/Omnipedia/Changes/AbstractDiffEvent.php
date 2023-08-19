<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Event\Omnipedia\Changes;

use Drupal\omnipedia_core\Entity\NodeInterface;
use Symfony\Contracts\EventDispatcher\Event;

/**
 * Omnipedia changes abstract diff event.
 */
abstract class AbstractDiffEvent extends Event {

  /**
   * Constructs this event object.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $currentNode
   *   The current wiki node object.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $previousNode
   *   The previous wiki node object.
   *
   * @param string $currentRendered
   *   The current wiki node revision rendered as HTML.
   *
   * @param string $previousRendered
   *   The previous wiki node revision rendered as HTML.
   */
  public function __construct(
    protected NodeInterface $currentNode,
    protected NodeInterface $previousNode,
    protected string        $currentRendered,
    protected string        $previousRendered,
  ) {}

  /**
   * Get the current wiki node revision's rendered HTML.
   *
   * @return string
   */
  public function getCurrentRendered(): string {
    return $this->currentRendered;
  }

  /**
   * Get the previous wiki node revision's rendered HTML.
   *
   * @return string
   */
  public function getPreviousRendered(): string {
    return $this->previousRendered;
  }

  /**
   * Set the current wiki node revision's rendered HTML.
   *
   * @param string $currentRendered
   *   The rendered HTML.
   */
  public function setCurrentRendered(string $currentRendered): void {
    $this->currentRendered = $currentRendered;
  }

  /**
   * Set the previous wiki node revision's rendered HTML.
   *
   * @param string $previousRendered
   */
  public function setPreviousRendered(string $previousRendered): void {
    $this->previousRendered = $previousRendered;
  }

}
