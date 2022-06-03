<?php

namespace Drupal\omnipedia_changes\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;

/**
 * The Omnipedia wiki node changes cache service interface.
 */
interface WikiNodeChangesCacheInterface {

  /**
   * Get the wiki node changes cache bin.
   *
   * @return \Drupal\Core\Cache\CacheBackendInterface
   */
  public function getCacheBin(): CacheBackendInterface;

  /**
   * Determine whether rendered changes for a provided wiki node are cached.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   The wiki node to check.
   *
   * @param boolean $allowInvalid
   *   Whether to check for rendered cached changes that are still present but
   *   have been invalidated. Defaults to false.
   *
   * @return boolean
   *   True if cached changes are found, false otherwise.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::get()
   *   See the $allow_invalid parameter in this method for use cases of our
   *   $allowInvalid parameter.
   */
  public function isCached(
    NodeInterface $node, bool $allowInvalid = false
  ): bool;

  /**
   * Get cached rendered changes for a provided wiki node, if any.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   The wiki node to attempt to get cached changes for.
   *
   * @param boolean $allowInvalid
   *   Whether to check for rendered cached changes that are still present but
   *   have been invalidated. Defaults to false.
   *
   * @return array|null
   *   A render array if rendered changes are found for the wiki node, or null
   *   otherwise.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::get()
   *   See the $allow_invalid parameter in this method for use cases of our
   *   $allowInvalid parameter.
   */
  public function get(NodeInterface $node, bool $allowInvalid = false): ?array;

  /**
   * Set rendered changes to the changes cache.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   The wiki node to set cached changes for.
   *
   * @param array $renderArray
   *   The render array to cache. This should contain cacheability metadata.
   */
  public function set(NodeInterface $node, array $renderArray): void;

  /**
   * Invalidate the changes cache for the provided wiki node.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   The wiki node to invalidate cached changes for.
   */
  public function invalidate(NodeInterface $node): void;

}
