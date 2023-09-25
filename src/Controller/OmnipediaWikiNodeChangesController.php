<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;
use Drupal\omnipedia_core\Service\WikiNodeRevisionInterface;
use Drupal\omnipedia_date\Service\TimelineInterface;
use Drupal\omnipedia_main_page\Service\MainPageResolverInterface;
use Drupal\typed_entity\EntityWrapperInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;

/**
 * Returns responses for the Omnipedia wiki node changes route.
 */
class OmnipediaWikiNodeChangesController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Constructs this controller; saves dependencies.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user proxy service.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\omnipedia_main_page\Service\MainPageResolverInterface $mainPageResolver
   *   The Omnipedia main page resolver service.
   *
   * @param \Drupal\omnipedia_date\Service\TimelineInterface $timeline
   *   The Omnipedia timeline service.
   *
   * @param \Drupal\typed_entity\EntityWrapperInterface $typedEntityRepositoryManager
   *   The Typed Entity repository manager.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface $wikiNodeChangesBuilder
   *   The Omnipedia wiki node changes builder service.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface $wikiNodeChangesCache
   *   The Omnipedia wiki node changes cache service.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface $wikiNodeChangesInfo
   *   The Omnipedia wiki node changes info service.
   *
   * @param \Drupal\omnipedia_core\Service\WikiNodeRevisionInterface $wikiNodeRevision
   *   The Omnipedia wiki node revision service.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   */
  public function __construct(
    protected readonly AccountProxyInterface            $currentUser,
    protected readonly LoggerInterface                  $loggerChannel,
    protected readonly MainPageResolverInterface        $mainPageResolver,
    protected readonly TimelineInterface                $timeline,
    protected readonly EntityWrapperInterface           $typedEntityRepositoryManager,
    protected readonly WikiNodeChangesBuilderInterface  $wikiNodeChangesBuilder,
    protected readonly WikiNodeChangesCacheInterface    $wikiNodeChangesCache,
    protected readonly WikiNodeChangesInfoInterface     $wikiNodeChangesInfo,
    protected readonly WikiNodeRevisionInterface        $wikiNodeRevision,
    protected $stringTranslation,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('logger.channel.omnipedia_changes'),
      $container->get('omnipedia_main_page.resolver'),
      $container->get('omnipedia.timeline'),
      $container->get('Drupal\typed_entity\RepositoryManager'),
      $container->get('omnipedia.wiki_node_changes_builder'),
      $container->get('omnipedia.wiki_node_changes_cache'),
      $container->get('omnipedia.wiki_node_changes_info'),
      $container->get('omnipedia.wiki_node_revision'),
      $container->get('string_translation'),
    );
  }

  /**
   * Checks access for the request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   A node object to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result. Access is granted if the provided node is a wiki node,
   *   the wiki node is not a main page, the wiki node has a previous revision,
   *   and $account has access to both the provided wiki node and its previous
   *   revision.
   */
  public function access(
    AccountInterface $account, NodeInterface $node,
  ): AccessResultInterface {

    /** @var \Drupal\omnipedia_core\WrappedEntities\NodeWithWikiInfoInterface|null */
    $previousWrappedNode = $this->typedEntityRepositoryManager->wrap(
      $node,
    )->getPreviousWikiRevision();

    return AccessResult::allowedIf(
      !$this->mainPageResolver->is($node) &&
      $node->access('view', $account) &&
      \is_object($previousWrappedNode) &&
      $previousWrappedNode->getEntity()->access('view', $account),
    )
    ->addCacheableDependency($node);

  }

  /**
   * Checks access for the build route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   A node object to check access for.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessBuild(
    AccountInterface $account, NodeInterface $node,
  ): AccessResultInterface {

    return $this->access($account, $node)->andIf(
      AccessResult::allowedIfHasPermission(
        $account, 'build omnipedia_changes',
      ),
    );

  }

  /**
   * Title callback for the route.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   A render array containing the changes title for this request.
   */
  public function title(NodeInterface $node): array {

    /** @var \Drupal\omnipedia_core\WrappedEntities\NodeWithWikiInfoInterface|null */
    $previousWrappedNode = $this->typedEntityRepositoryManager->wrap(
      $node,
    )->getPreviousWikiRevision();

    return [
      '#markup'       => $this->t(
        '<span class="page-title__primary">@title<span class="page-title__glue">: </span></span><span class="page-title__secondary">Changes since @date</span>',
        [
          '@title'  => $node->getTitle(),
          '@date'   => $this->timeline->getDateFormatted(
            $previousWrappedNode->getWikiDate(), 'short'
          ),
        ]
      ),
      '#allowed_tags' => Xss::getHtmlTagList(),
    ];

  }

  /**
   * Content callback for the route.
   *
   * Note that this intentionally checks for and returns invalidated cached
   * changes if available to minimize the amount of time the placeholder would
   * be shown. We're erring on the side of showing potentially out of date
   * changes rather than none at all.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   A node object.
   *
   * @return array
   *   A render array containing the changes content for this request, or a
   *   placeholder render array if the changes have not yet been built.
   */
  public function view(NodeInterface $node): array {

    if (!$this->wikiNodeChangesCache->isCached($node, true)) {

      // Log this uncached view attempt in case it's useful data for debugging
      // or future optimizations.
      $this->loggerChannel->debug(
        'Wiki node changes not cached: user <code>%uid</code> requested node <code>%nid</code> with cache ID <code>%cid</code><br>Available cache IDs for this node:<pre>%cids</pre>Current user\'s roles:<pre>%roles</pre>',
        [
          '%uid'    => $this->currentUser->id(),
          '%nid'    => $node->nid->getString(),
          '%cid'    => $this->wikiNodeChangesInfo->getCacheId(
            $node->nid->getString()
          ),
          '%cids'   => \print_r($this->wikiNodeChangesInfo->getCacheIds(
            $node->nid->getString()
          ), true),
          '%roles'  => \print_r($this->currentUser->getRoles(), true),
        ]
      );

      return $this->wikiNodeChangesBuilder->buildPlaceholder($node);

    }

    return $this->wikiNodeChangesBuilder->build($node, true);

  }

  /**
   * Content callback for the build route.
   *
   * This invalidates the changes for the provided wiki node, builds the
   * changes, and then redirects to the changes route.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   A node object.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect response object.
   */
  public function viewBuild(NodeInterface $node): RedirectResponse {

    $this->wikiNodeChangesCache->invalidate($node);

    $this->wikiNodeChangesBuilder->build($node);

    /** @var \Drupal\Core\GeneratedUrl */
    $generatedUrl = Url::fromRoute('entity.node.omnipedia_changes', [
      'node' => $node->nid->getString(),
    ])->toString(true);

    return (new RedirectResponse(
      $generatedUrl->getGeneratedUrl(), 302,
    ));

  }

}
