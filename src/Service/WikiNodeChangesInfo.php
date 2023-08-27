<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;
use Drupal\omnipedia_core\Entity\WikiNodeInfo;
use Drupal\omnipedia_user\Service\PermissionHashesInterface;

/**
 * The Omnipedia wiki node changes info service.
 */
class WikiNodeChangesInfo implements WikiNodeChangesInfoInterface {

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $cacheContextsManager
   *   The Drupal cache contexts manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entity type manager.
   *
   * @param \Drupal\omnipedia_user\Service\PermissionHashesInterface $permissionHashes
   *   The Omnipedia permission hashes service.
   */
  public function __construct(
    protected readonly CacheContextsManager       $cacheContextsManager,
    protected readonly EntityTypeManagerInterface $entityTypeManager,
    protected readonly PermissionHashesInterface  $permissionHashes,
  ) {}

  /**
   * {@inheritdoc}
   *
   * @todo Add i18n support.
   */
  public function getCacheIds(string $nid): array {

    /** @var string[] */
    $permissionHashes = $this->permissionHashes->getPermissionHashes();

    // These are hard-coded for now. We only render on one theme, and currently
    // only have content in English.
    /** @var string[] */
    $cacheKeys = $this->cacheContextsManager->convertTokensToKeys([
      'languages:language_interface',
      'theme',
    ])->getKeys();

    /** @var string[] */
    $variations = [];

    // Build the variations, including the 'user.permissions' cache context in
    // the exact format CacheContextsManager::convertTokensToKeys() would
    // generate for the current user - instead we're building it for all
    // permission hashes currently represented by users in the site.
    foreach ($permissionHashes as $roles => $hash) {
      $variations[$roles] = $nid . ':' . \implode(
        ':', \array_merge($cacheKeys, ['[user.permissions]=' . $hash])
      );
    }

    return $variations;

  }

  /**
   * {@inheritdoc}
   */
  public function getAllCacheIds(): array {

    // This builds and executes a \Drupal\Core\Entity\Query\QueryInterface to
    // get all available wiki node IDs (nids).
    /** @var array */
    $nids = ($this->entityTypeManager->getStorage('node')->getQuery())
      ->condition('type', WikiNodeInfo::TYPE)
      // Disable access checking so that this works as expected when invoked via
      // Drush at the commandline.
      ->accessCheck(false)
      ->execute();

    $info = [];

    foreach ($nids as $revisionId => $nid) {
      $info[$nid] = $this->getCacheIds($nid);
    }

    return $info;

  }

  /**
   * {@inheritdoc}
   */
  public function getCacheId(string $nid): string {

    // Build the cache keys to vary by based on these hard-coded cache contexts.
    // The cache contexts manager will automatically populate these with the
    // relevant values, e.g. the language code or current user's permissions
    // hash.
    /** @var array */
    $cacheKeys = $this->cacheContextsManager->convertTokensToKeys([
      'languages:language_interface',
      'theme',
      'user.permissions',
    ])->getKeys();

    return $nid . ':' . \implode(':', $cacheKeys);

  }

  /**
   * {@inheritdoc}
   */
  public function getPlaceholderCacheTag(string $nid): string {

    return \implode(':', [
      'omnipedia_wiki_node_changes_placeholder',
      $nid,
      $this->permissionHashes->getPermissionHash(),
    ]);

  }

  /**
   * {@inheritdoc}
   */
  public function getPlaceholderCacheMetadata(string $nid): array {

    return [
      // These are the same contexts as the built diffs.
      'contexts' => [
        'languages:language_interface',
        'theme',
        'user.permissions',
      ],
      'max-age'  => Cache::PERMANENT,
      'tags'     => [$this->getPlaceholderCacheTag($nid)],
    ];

  }

}
