<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Controller;

use Drupal\Component\Utility\Xss;
use Drupal\Core\Access\AccessResult;
use Drupal\Core\Access\AccessResultInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\TrustedRedirectResponse;
use Drupal\Core\Session\AccountInterface;
use Drupal\Core\Session\AccountProxyInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\Url;
use Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;
use Drupal\omnipedia_date\Service\TimelineInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Returns responses for the Omnipedia wiki node changes route.
 */
class OmnipediaWikiNodeChangesController implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Our logger channel.
   *
   * @var \Psr\Log\LoggerInterface
   */
  protected LoggerInterface $loggerChannel;

  /**
   * The Omnipedia timeline service.
   *
   * @var \Drupal\omnipedia_date\Service\TimelineInterface
   */
  protected TimelineInterface $timeline;

  /**
   * The Omnipedia wiki node changes builder service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface
   */
  protected WikiNodeChangesBuilderInterface $wikiNodeChangesBuilder;

  /**
   * The Omnipedia wiki node changes cache service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface
   */
  protected WikiNodeChangesCacheInterface $wikiNodeChangesCache;

  /**
   * The Omnipedia wiki node changes info service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface
   */
  protected WikiNodeChangesInfoInterface $wikiNodeChangesInfo;

  /**
   * Constructs this controller; saves dependencies.
   *
   * @param \Drupal\Core\Session\AccountProxyInterface $currentUser
   *   The current user proxy service.
   *
   * @param \Psr\Log\LoggerInterface $loggerChannel
   *   Our logger channel.
   *
   * @param \Drupal\omnipedia_date\Service\TimelineInterface $timeline
   *   The Omnipedia timeline service.
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
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   */
  public function __construct(
    AccountProxyInterface           $currentUser,
    LoggerInterface                 $loggerChannel,
    TimelineInterface               $timeline,
    WikiNodeChangesBuilderInterface $wikiNodeChangesBuilder,
    WikiNodeChangesCacheInterface   $wikiNodeChangesCache,
    WikiNodeChangesInfoInterface    $wikiNodeChangesInfo,
    protected $stringTranslation,
  ) {

    $this->currentUser            = $currentUser;
    $this->loggerChannel          = $loggerChannel;
    $this->timeline               = $timeline;
    $this->wikiNodeChangesBuilder = $wikiNodeChangesBuilder;
    $this->wikiNodeChangesCache   = $wikiNodeChangesCache;
    $this->wikiNodeChangesInfo    = $wikiNodeChangesInfo;

  }

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('current_user'),
      $container->get('logger.channel.omnipedia_changes'),
      $container->get('omnipedia.timeline'),
      $container->get('omnipedia.wiki_node_changes_builder'),
      $container->get('omnipedia.wiki_node_changes_cache'),
      $container->get('omnipedia.wiki_node_changes_info'),
      $container->get('string_translation'),
    );
  }

  /**
   * Checks access for the request.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result. Access is granted if the provided node is a wiki node,
   *   the wiki node is not a main page, the wiki node has a previous revision,
   *   and $account has access to both the provided wiki node and its previous
   *   revision.
   */
  public function access(
    AccountInterface $account, NodeInterface $node
  ): AccessResultInterface {

    /** \Drupal\omnipedia_core\Entity\NodeInterface|null */
    $previousNode = $node->getPreviousWikiNodeRevision();

    return AccessResult::allowedIf(
      !$node->isMainPage() &&
      $node->access('view', $account) &&
      \is_object($previousNode) &&
      $previousNode->access('view', $account)
    )
    ->addCacheableDependency($node);

  }

  /**
   * Checks access for the build route.
   *
   * @param \Drupal\Core\Session\AccountInterface $account
   *   Run access checks for this account.
   *
   * @return \Drupal\Core\Access\AccessResultInterface
   *   The access result.
   */
  public function accessBuild(
    AccountInterface $account, NodeInterface $node
  ): AccessResultInterface {

    return $this->access($account, $node)->andIf(
      AccessResult::allowedIfHasPermission(
        $account, 'build omnipedia_changes'
      )
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

    /** \Drupal\omnipedia_core\Entity\NodeInterface|null */
    $previousNode = $node->getPreviousWikiNodeRevision();

    return [
      '#markup'       => $this->t(
        '<span class="page-title__primary">@title<span class="page-title__glue">: </span></span><span class="page-title__secondary">Changes since @date</span>',
        [
          '@title'  => $node->getTitle(),
          '@date'   => $this->timeline->getDateFormatted(
            $previousNode->getWikiNodeDate(), 'short'
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
   * @return \Drupal\Core\Routing\TrustedRedirectResponse
   *   A trusted redirect response object.
   */
  public function viewBuild(NodeInterface $node): TrustedRedirectResponse {

    $this->wikiNodeChangesCache->invalidate($node);

    $this->wikiNodeChangesBuilder->build($node);

    /** @var \Drupal\Core\GeneratedUrl */
    $generatedUrl = Url::fromRoute('entity.node.omnipedia_changes', [
      'node' => $node->nid->getString(),
    ])->toString(true);

    return (new TrustedRedirectResponse(
      $generatedUrl->getGeneratedUrl(), 302,
    ))->addCacheableDependency($node)->addCacheableDependency($generatedUrl);

  }

}
