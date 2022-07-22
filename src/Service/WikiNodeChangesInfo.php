<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Cache\Context\CacheContextsManager;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesUserInterface;
use Drupal\omnipedia_core\Entity\Node;
use Drupal\omnipedia_core\Entity\NodeInterface;

/**
 * The Omnipedia wiki node changes info service.
 */
class WikiNodeChangesInfo implements WikiNodeChangesInfoInterface {

  /**
   * The Drupal cache contexts manager.
   *
   * @var \Drupal\Core\Cache\Context\CacheContextsManager
   */
  protected $cacheContextsManager;

  /**
   * The Drupal node entity storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected $nodeStorage;

  /**
   * The Drupal user role entity storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected $roleStorage;

  /**
   * The Drupal user entity storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected $userStorage;

  /**
   * The Omnipedia wiki node changes user service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesUserInterface
   */
  protected $wikiNodeChangesUser;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Cache\Context\CacheContextsManager $cacheContextsManager
   *   The Drupal cache contexts manager.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entity type manager.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesUserInterface $wikiNodeChangesUser
   *   The Omnipedia wiki node changes user service.
   */
  public function __construct(
    CacheContextsManager          $cacheContextsManager,
    EntityTypeManagerInterface    $entityTypeManager,
    WikiNodeChangesUserInterface  $wikiNodeChangesUser
  ) {

    $this->cacheContextsManager = $cacheContextsManager;
    $this->nodeStorage          = $entityTypeManager->getStorage('node');
    $this->roleStorage          = $entityTypeManager->getStorage('user_role');
    $this->userStorage          = $entityTypeManager->getStorage('user');
    $this->wikiNodeChangesUser  = $wikiNodeChangesUser;

  }

  /**
   * {@inheritdoc}
   *
   * @todo Add i18n support.
   */
  public function getCacheIds(string $nid): array {

    /** @var string[] */
    $permissionHashes = $this->wikiNodeChangesUser->getPermissionHashes();

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
    $nids = ($this->nodeStorage->getQuery())
      ->condition('type', Node::getWikiNodeType())
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
      $this->wikiNodeChangesUser->getPermissionHash(),
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
