<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\node\NodeInterface;

/**
 * The Omnipedia wiki node changes builder service interface.
 */
interface WikiNodeChangesBuilderInterface {

  /**
   * Build changes content for a wiki node.
   *
   * This renders the current revision node and the previous revision node,
   * generates the diff via the HTML Diff service that the Diff module provides,
   * and alters/adjust the output as follows before returning it.
   *
   * Note that this doesn't do any access checking, so code that calls this is
   * responsible for not displaying information about nodes the user does not
   * have access to.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @param boolean $allowInvalid
   *   Whether to check for rendered cached changes that are still present but
   *   have been invalidated. Defaults to false.
   *
   * @return array
   *   A render array containing the changes content for the provided wiki node.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::get()
   *   See the $allow_invalid parameter in this method for use cases of our
   *   $allowInvalid parameter.
   */
  public function build(NodeInterface $node, bool $allowInvalid = false): array;

  /**
   * Build placeholder content for a wiki node.
   *
   * This returns a render array with placeholder content and cache metadata
   * that's invalidated as soon as this wiki node's changes are built.
   *
   * @param \Drupal\node\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   A render array with placeholder content.
   */
  public function buildPlaceholder(NodeInterface $node): array;

}
