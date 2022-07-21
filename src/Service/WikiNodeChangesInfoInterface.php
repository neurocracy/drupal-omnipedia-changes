<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\omnipedia_core\Entity\NodeInterface;

/**
 * The Omnipedia wiki node changes info service interface.
 */
interface WikiNodeChangesInfoInterface {

  /**
   * Get all cache IDs for a provided wiki node's changes.
   *
   * This will generate all the cache IDs for a provided wiki node, including
   * for each 'user.permissions' cache context, allowing for preemptive caching.
   *
   * Note that this only generates variations based the node ID (nid),
   * language, negotiated theme, and permissions hashes. A more advanced
   * version of this could replicate what the render cache does, but we don't
   * anticipate requiring that level of granular variation; this is a
   * compromise between having enough variations so that we don't expose any
   * admin stuff (e.g. contextual links) or create security issues, and
   * avoiding caching more variations than we expect to need for the sake of
   * performance.
   *
   * @param string $nid
   *   The wiki node ID (nid) to get changes cache IDs for.
   *
   * @return string[]
   *   An array of cache IDs, keyed by a comma-separated list of the role ID
   *   combination each corresponds to.
   *
   * @see \Drupal\Core\Render\RenderCache::createCacheID()
   *   Drupal core render cache ID generation for reference.
   */
  public function getCacheIds(string $nid): array;

  /**
   * Get all cache IDs for all wiki nodes.
   *
   * Note that this bypasses access so all wiki node are listed, so code that
   * calls this is responsible for not exposing content the current user does
   * not have access to.
   *
   * @return array[]
   *   A multi-dimensional array. Keys are the node IDs (nids), each containing
   *   an array of cache IDs for that given node in the format returned by
   *   self::getCacheIds().
   *
   * @see self::getCacheIds()
   *   Describes the details of what is returned.
   */
  public function getAllCacheIds(): array;

  /**
   * Get a cache ID for a provided wiki node's changes for the current user.
   *
   * Note that this only generates variations based the node ID (nid), language,
   * negotiated theme, and the current user's permissions hash. A more advanced
   * version of this could replicate what the render cache does, but we don't
   * anticipate requiring that level of granular variation; this is a compromise
   * between having enough variations so that we don't expose any admin stuff
   * (e.g. contextual links) or create security issues, and avoiding caching
   * more variations than we expect to need for the sake of performance.
   *
   * @param string $nid
   *   The wiki node ID (nid) to get a changes cache ID for.
   *
   * @return string
   *   The cache ID for the current user.
   *
   * @todo Do we need to support fetching for other users?
   *
   * @see \Drupal\Core\Render\RenderCache::createCacheID()
   *   Drupal core render cache ID generation for reference.
   */
  public function getCacheId(string $nid): string;

  /**
   * Get the placeholder content cache tag for a provided node ID (nid).
   *
   * Note that this is for the current user.
   *
   * @param string $nid
   *   The wiki node ID (nid) to get the placeholder content cache tag for.
   *
   * @return string
   *   The cache tag for the current user.
   */
  public function getPlaceholderCacheTag(string $nid): string;

  /**
   * Get the placeholder content cache metadata for a provide node ID (nid).
   *
   * Note that this is for the current user.
   *
   * @param string $nid
   *   The wiki node ID (nid) to get the placeholder content cache metadata for.
   *
   * @return array
   *   A cache metadata array, suitable for use as #cache in render arrays.
   */
  public function getPlaceholderCacheMetadata(string $nid): array;

}
