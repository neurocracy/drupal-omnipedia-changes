<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Event\Omnipedia\Changes;

use Drupal\omnipedia_core\Entity\NodeInterface;
use Symfony\Component\EventDispatcher\Event;

/**
 * Omnipedia changes abstract diff event.
 */
abstract class AbstractDiffEvent extends Event {

  /**
   * The current wiki node object.
   *
   * @var \Drupal\omnipedia_core\Entity\NodeInterface
   */
  protected $currentNode;

  /**
   * The previous wiki node object.
   *
   * @var \Drupal\omnipedia_core\Entity\NodeInterface
   */
  protected $previousNode;

  /**
   * The current wiki node revision rendered as HTML.
   *
   * @var string
   */
  protected $currentRendered;

  /**
   * The previous wiki node revision rendered as HTML.
   *
   * @var string
   */
  protected $previousRendered;

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
    NodeInterface $currentNode,
    NodeInterface $previousNode,
    string        $currentRendered,
    string        $previousRendered
  ) {

    $this->currentNode  = $currentNode;
    $this->previousNode = $previousNode;

    $this->setCurrentRendered($currentRendered);
    $this->setPreviousRendered($previousRendered);

  }

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
