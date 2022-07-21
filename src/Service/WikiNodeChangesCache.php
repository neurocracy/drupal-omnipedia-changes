<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\Core\Cache\CacheBackendInterface;
use Drupal\Core\Cache\CacheTagsInvalidatorInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;

/**
 * The Omnipedia wiki node changes cache service.
 *
 * Note that this is not the same as the 'cache.omnipedia_wiki_node_changes'
 * cache bin, but an abstraction on top of that cache bin to set and get wiki
 * node changes to/from that cache bin.
 */
class WikiNodeChangesCache implements WikiNodeChangesCacheInterface {

  /**
   * The Drupal cache tags invalidator service.
   *
   * @var \Drupal\Core\Cache\CacheTagsInvalidatorInterface
   */
  protected $cacheTagsInvalidator;

  /**
   * The Omnipedia wiki node changes cache bin.
   *
   * @var \Drupal\Core\Cache\CacheBackendInterface
   */
  protected $changesCache;

  /**
   * The Omnipedia wiki node changes info service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface
   */
  protected $wikiNodeChangesInfo;

  /**
   * Constructs this service object.
   *
   * @param \Drupal\Core\Cache\CacheBackendInterface $changesCache
   *   The Omnipedia wiki node changes cache bin.
   *
   * @param \Drupal\Core\Cache\CacheTagsInvalidatorInterface $cacheTagsInvalidator
   *   The Drupal cache tags invalidator service.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface $wikiNodeChangesInfo
   *   The Omnipedia wiki node changes info service.
   */
  public function __construct(
    CacheBackendInterface         $changesCache,
    CacheTagsInvalidatorInterface $cacheTagsInvalidator,
    WikiNodeChangesInfoInterface  $wikiNodeChangesInfo
  ) {

    // Save dependencies.
    $this->changesCache         = $changesCache;
    $this->cacheTagsInvalidator = $cacheTagsInvalidator;
    $this->wikiNodeChangesInfo  = $wikiNodeChangesInfo;

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheBin(): CacheBackendInterface {
    return $this->changesCache;
  }

  /**
   * {@inheritdoc}
   */
  public function isCached(
    NodeInterface $node, bool $allowInvalid = false
  ): bool {
    return \is_object($this->changesCache->get(
      $this->wikiNodeChangesInfo->getCacheId($node->nid->getString()),
      $allowInvalid
    ));
  }

  /**
   * {@inheritdoc}
   */
  public function get(NodeInterface $node, bool $allowInvalid = false): ?array {

    if ($this->isCached($node, $allowInvalid)) {
      return $this->changesCache->get(
        $this->wikiNodeChangesInfo->getCacheId($node->nid->getString()),
        $allowInvalid
      )->data;
    }

    return null;

  }

  /**
   * {@inheritdoc}
   */
  public function set(NodeInterface $node, array $renderArray): void {

    /** @var \Drupal\Core\Render\BubbleableMetadata */
    $bubbleableMetadata = BubbleableMetadata::createFromRenderArray(
      $renderArray
    );

    $this->changesCache->set(
      $this->wikiNodeChangesInfo->getCacheId($node->nid->getString()),
      $renderArray,
      $bubbleableMetadata->getCacheMaxAge(),
      $bubbleableMetadata->getCacheTags()
    );

    // Invalidate the placeholder cache tag for this wiki node in the current
    // context (i.e. user).
    $this->cacheTagsInvalidator->invalidateTags([
      $this->wikiNodeChangesInfo->getPlaceholderCacheTag(
        $node->nid->getString()
      ),
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function invalidate(NodeInterface $node): void {
    $this->changesCache->invalidate(
      $this->wikiNodeChangesInfo->getCacheId($node->nid->getString())
    );
  }

}
