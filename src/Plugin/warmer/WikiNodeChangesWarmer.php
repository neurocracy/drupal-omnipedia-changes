<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Plugin\warmer;

use Drupal\Component\Plugin\Exception\PluginException;
use Drupal\Core\Form\SubformStateInterface;
use Drupal\Core\Session\AccountSwitcherInterface;
use Drupal\Core\Utility\Error;
use Drupal\node\NodeStorageInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;
use Drupal\omnipedia_user\Service\RepresentativeRenderUserInterface;
use Drupal\user\RoleStorageInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;
use Drupal\warmer\Plugin\WarmerPluginBase;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * The Omnipedia wiki node changes cache warmer plug-in.
 *
 * @Warmer(
 *   id           = "omnipedia_wiki_node_changes",
 *   label        = @Translation("Omnipedia: wiki page changes"),
 *   description  = @Translation("Warms the wiki page changes cache by building any changes not yet cached.")
 * )
 *
 * @see \Drupal\warmer\Plugin\WarmerInterface
 *   Documentation for public methods.
 *
 * @see \Drupal\warmer_entity\Plugin\warmer\EntityWarmer
 *   Example/reference of a warmer plug-in shipped with the Warmer module.
 *
 * @see \Drupal\warmer_cdn\Plugin\warmer\CdnWarmer
 *   Example/reference of a warmer plug-in shipped with the Warmer module.
 */
class WikiNodeChangesWarmer extends WarmerPluginBase {

  /**
   * An array of all possible cache IDs for wiki nodes.
   *
   * @var string[]
   *
   * @see \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface::getAllCacheIds()
   *   Built from the return value of this, but with the array structure
   *   flattened - instead of keying by node ID (nid), we key by node ID and
   *   roles, to allow for easy counting of progress.
   */
  protected array $cacheIds;

  /**
   * All user role entities, keyed by role ID (rid).
   *
   * @var \Drupal\user\RoleInterface[]
   */
  protected array $allRoles;

  /**
   * The Drupal account switcher service.
   *
   * @var \Drupal\Core\Session\AccountSwitcherInterface
   */
  protected AccountSwitcherInterface $accountSwitcher;

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $loggerChannel;

  /**
   * The Drupal node entity storage.
   *
   * @var \Drupal\node\NodeStorageInterface
   */
  protected NodeStorageInterface $nodeStorage;

  /**
   * The Omnipedia representative render user service.
   *
   * @var \Drupal\omnipedia_user\Service\RepresentativeRenderUserInterface
   */
  protected RepresentativeRenderUserInterface $representativeRenderUser;

  /**
   * The Drupal user role entity storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected RoleStorageInterface $roleStorage;

  /**
   * The Drupal user entity storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * The Omnipedia wiki node changes builder service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface
   */
  protected WikiNodeChangesBuilderInterface $wikiNodeChangesBuilder;

  /**
   * The Omnipedia wiki node changes info service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface
   */
  protected WikiNodeChangesInfoInterface $wikiNodeChangesInfo;

  /**
   * {@inheritdoc}
   */
  public static function create(
    ContainerInterface $container,
    array $configuration, $pluginId, $pluginDefinition
  ) {

    /** @var \Drupal\warmer\Plugin\WarmerInterface */
    $instance = parent::create(
      $container, $configuration, $pluginId, $pluginDefinition
    );

    $instance->setAccountSwitcher(
      $container->get('account_switcher')
    );

    $instance->setLoggerChannel(
      $container->get('logger.channel.omnipedia_changes')
    );

    $instance->setNodeStorage(
      $container->get('entity_type.manager')->getStorage('node')
    );

    $instance->setRepresentativeRenderUser(
      $container->get('omnipedia_user.representative_render_user')
    );

    $instance->setRoleStorage(
      $container->get('entity_type.manager')->getStorage('user_role')
    );

    $instance->setUserStorage(
      $container->get('entity_type.manager')->getStorage('user')
    );

    $instance->setWikiNodeChangesBuilder(
      $container->get('omnipedia.wiki_node_changes_builder')
    );

    $instance->setWikiNodeChangesInfo(
      $container->get('omnipedia.wiki_node_changes_info')
    );

    return $instance;

  }

  /**
   * Injects the Drupal account switcher service.
   *
   * @param \Drupal\Core\Session\AccountSwitcherInterface $accountSwitcher
   *   The Drupal account switcher service.
   */
  public function setAccountSwitcher(
    AccountSwitcherInterface $accountSwitcher
  ): void {
    $this->accountSwitcher = $accountSwitcher;
  }

  /**
   * Injects the logger channel.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   */
  public function setLoggerChannel(LoggerInterface $loggerChannel): void {
    $this->loggerChannel = $loggerChannel;
  }

  /**
   * Injects the Drupal node entity storage.
   *
   * @param \Drupal\node\NodeStorageInterface $nodeStorage
   *   The Drupal node entity storage.
   */
  public function setNodeStorage(NodeStorageInterface $nodeStorage): void {
    $this->nodeStorage = $nodeStorage;
  }

  /**
   * Injects the Omnipedia representative render user service.
   *
   * @param \Drupal\omnipedia_user\Service\RepresentativeRenderUserInterface $representativeRenderUser
   *   The Omnipedia representative render user service.
   */
  public function setRepresentativeRenderUser(
    RepresentativeRenderUserInterface $representativeRenderUser
  ): void {
    $this->representativeRenderUser = $representativeRenderUser;
  }

  /**
   * Injects the Drupal user role entity storage.
   *
   * @param \Drupal\user\RoleStorageInterface $roleStorage
   *   The Drupal user role entity storage.
   */
  public function setRoleStorage(RoleStorageInterface $roleStorage): void {
    $this->roleStorage = $roleStorage;
  }

  /**
   * Injects the Drupal user entity storage.
   *
   * @param \Drupal\user\UserStorageInterface $userStorage
   *   The Drupal user entity storage.
   */
  public function setUserStorage(UserStorageInterface $userStorage): void {
    $this->userStorage = $userStorage;
  }

  /**
   * Injects the Omnipedia wiki node changes builder service.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface $wikiNodeChangesBuilder
   *   The Omnipedia wiki node changes builder service.
   */
  public function setWikiNodeChangesBuilder(
    WikiNodeChangesBuilderInterface $wikiNodeChangesBuilder
  ): void {
    $this->wikiNodeChangesBuilder = $wikiNodeChangesBuilder;
  }

  /**
   * Injects the Omnipedia wiki node changes info service.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface $wikiNodeChangesInfo
   *   The Omnipedia wiki node changes info service.
   */
  public function setWikiNodeChangesInfo(
    WikiNodeChangesInfoInterface $wikiNodeChangesInfo
  ): void {
    $this->wikiNodeChangesInfo = $wikiNodeChangesInfo;
  }

  /**
   * {@inheritdoc}
   */
  public function defaultConfiguration() {

    /** @var array */
    $config = parent::defaultConfiguration();

    // Significantly reduce the batch size as some diffs can take a while.
    $config['batchSize'] = 5;

    return $config;

  }

  /**
   * {@inheritdoc}
   */
  public function loadMultiple(array $ids = []) {

    /** @var array */
    $items = [];

    foreach ($ids as $id => $cacheId) {

      list($nid, $roles) = \explode(':', $id);

      /** \Drupal\omnipedia_core\Entity\NodeInterface|null */
      $node = $this->nodeStorage->load($nid);

      /** \Drupal\omnipedia_core\Entity\NodeInterface|null */
      $previousNode = $node->getPreviousWikiNodeRevision();

      // Skip if there's no previous wiki node revision.
      if (!\is_object($previousNode)) {
        continue;
      }

      /** @var \Drupal\user\UserInterface|null */
      $renderUser = $this->representativeRenderUser->getUserToRenderAs(
        \explode(',', $roles),
        function(UserInterface $user) use ($node, $previousNode) {
          // Check that the provided user can access both the current and
          // previous wiki nodes.
          return $node->access('view', $user) && $previousNode->access(
            'view', $user
          );
        }
      );

      // Skip this item if we couldn't find a user to render it as.
      if (!\is_object($renderUser)) {
        continue;
      }

      $items[$id] = [
        'node'          => $node,
        'previousNode'  => $previousNode,
        'user'          => $renderUser,
      ];

    }

    return $items;

  }

  /**
   * {@inheritdoc}
   */
  public function warmMultiple(array $items = []) {

    /** @var integer */
    $count = 0;

    foreach ($items as $key => $item) {

      // Switch over to the provided user account for rendering.
      $this->accountSwitcher->switchTo($item['user']);

      // Attempt to build the changes render array, which will automatically
      // cache it if it isn't already cached, or will return it from cache.
      try {

        $renderArray = $this->wikiNodeChangesBuilder->build($item['node']);

      } catch (PluginException $exception) {

        // Log the exception.
        //
        // @see \watchdog_exception()
        //   We're replicating what this function does, but using the injected
        //   logger channel.
        $this->loggerChannel->error(
          '%type: @message in %function (line %line of %file).',
          Error::decodeException($exception)
        );

      }

      // Switch back to the current user.
      $this->accountSwitcher->switchBack();

      // Increment the counter if the render array isn't empty or null.
      if (!empty($renderArray)) {
        $count++;
      }

    }

    return $count;

  }

  /**
   * {@inheritdoc}
   */
  public function buildIdsBatch($cursor) {

    if (!isset($this->cacheIds)) {

      $this->cacheIds = [];

      /** @var array[] */
      $cacheIds = $this->wikiNodeChangesInfo->getAllCacheIds();

      // This flattens the array structure.
      foreach ($cacheIds as $nid => $nodeCacheIds) {
        foreach ($nodeCacheIds as $roles => $cacheId) {
          $this->cacheIds[$nid . ':' . $roles] = $cacheId;
        }
      }

    }

    /** @var int|false */
    $cursorPosition = \is_null($cursor) ? -1 :
      // Get the integer offset given the current cursor. Note that we have to
      // use \array_values($this->cacheIds) to be able to + 1 increment the
      // offset in the \array_slice(), since that array uses string keys.
      \array_search($cursor, \array_values($this->cacheIds));

    // If \array_search() returned false, bail returning an empty array.
    if ($cursorPosition === false) {
      return [];
    }

    return \array_slice(
      $this->cacheIds, $cursorPosition + 1, (int) $this->getBatchSize()
    );

  }

  /**
   * {@inheritdoc}
   */
  public function addMoreConfigurationFormElements(
    array $form, SubformStateInterface $formState
  ) {

    // We don't have any form elements to add.
    return $form;

  }

}
