<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Render\BubbleableMetadata;
use Drupal\Core\Render\RenderContext;
use Drupal\Core\Render\RendererInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\Core\StringTranslation\TranslationInterface;
use Drupal\omnipedia_changes\Event\OmnipediaContentChangesEventInterface;
use Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent;
use Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostRenderPreBuildEvent;
use Drupal\omnipedia_changes\Service\WikiNodeChangesBuilderInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesTrait;
use Drupal\omnipedia_core\Entity\NodeInterface;
use HtmlDiffAdvancedInterface;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventDispatcherInterface;

/**
 * The Omnipedia wiki node changes builder service.
 */
class WikiNodeChangesBuilder implements WikiNodeChangesBuilderInterface, WikiNodeChangesCssClassesInterface {

  use StringTranslationTrait;
  use WikiNodeChangesCssClassesTrait;

  /**
   * The Drupal entity type manager.
   *
   * @var \Drupal\Core\Entity\EntityTypeManagerInterface
   */
  protected EntityTypeManagerInterface $entityTypeManager;

  /**
   * The Symfony event dispatcher service.
   *
   * @var \Symfony\Component\EventDispatcher\EventDispatcherInterface
   */
  protected EventDispatcherInterface $eventDispatcher;

  /**
   * The HTML diff service provided by the Diff module.
   *
   * @var \HtmlDiffAdvancedInterface
   */
  protected HtmlDiffAdvancedInterface $htmlDiff;

  /**
   * The Drupal renderer service.
   *
   * @var \Drupal\Core\Render\RendererInterface
   */
  protected RendererInterface $renderer;

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
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entity type manager.
   *
   * @param \Symfony\Component\EventDispatcher\EventDispatcherInterface $eventDispatcher
   *   The Symfony event dispatcher service.
   *
   * @param \HtmlDiffAdvancedInterface $htmlDiff
   *   The HTML diff service provided by the Diff module.
   *
   * @param \Drupal\Core\Render\RendererInterface $renderer
   *   The Drupal renderer service.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface $wikiNodeChangesCache
   *   The Omnipedia wiki node changes cache service.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesInfoInterface $wikiNodeChangesInfo
   *   The Omnipedia wiki node changes info service.
   *
   * @see $this->alterHtmlDiffConfig()
   */
  public function __construct(
    EntityTypeManagerInterface    $entityTypeManager,
    EventDispatcherInterface      $eventDispatcher,
    HtmlDiffAdvancedInterface     $htmlDiff,
    RendererInterface             $renderer,
    TranslationInterface          $stringTranslation,
    WikiNodeChangesCacheInterface $wikiNodeChangesCache,
    WikiNodeChangesInfoInterface  $wikiNodeChangesInfo
  ) {

    $this->entityTypeManager    = $entityTypeManager;
    $this->eventDispatcher      = $eventDispatcher;
    $this->htmlDiff             = $htmlDiff;
    $this->renderer             = $renderer;
    $this->stringTranslation    = $stringTranslation;
    $this->wikiNodeChangesCache = $wikiNodeChangesCache;
    $this->wikiNodeChangesInfo  = $wikiNodeChangesInfo;

    $this->alterHtmlDiffConfig();

  }

  /**
   * Alter the HTML diff configuration used for diffing.
   *
   * The following changes are made:
   *
   * - Disables the use of HTML Purifier to avoid having to wade through that
   *   configuration nightmare to whitelist attributes (e.g. style) and elements
   *   (such as SVG icons). Drupal's render and filtering systems should take
   *   care of any security stuff for us as we render before passing the markup
   *   to the HTML diff service. This config option requires @link
   *   https://github.com/caxy/php-htmldiff/releases/tag/v0.1.11
   *   caxy/php-htmldiff >= 0.1.11 @endLink which has been specified in this
   *   module's composer.json.
   *
   * - Disables special handling of <a> elements diffing, which would highlight
   *   changed href attributes. The only instance where this currently happens
   *   without the link text changing is when an internal wiki link changes to
   *   point to the new date's revision, which would be irrelevant to highlight.
   */
  protected function alterHtmlDiffConfig(): void {

    /** @var \Caxy\HtmlDiff\HtmlDiffConfig */
    $config = $this->htmlDiff->getConfig();

    $config->setPurifierEnabled(false);

    /** @var array */
    $isolatedDiffElements = $config->getIsolatedDiffTags();

    if (isset($isolatedDiffElements['a'])) {
      unset($isolatedDiffElements['a']);
    }

    $config->setIsolatedDiffTags($isolatedDiffElements);

  }

  /**
   * Get diff content for a wiki node.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   A wiki node object to get the diff content for.
   *
   * @param boolean $allowInvalid
   *   Whether to check for rendered cached changes that are still present but
   *   have been invalidated. Defaults to false.
   *
   * @return array
   *   The diff render array.
   *
   * @see \Drupal\Core\Cache\CacheBackendInterface::get()
   *   See the $allow_invalid parameter in this method for use cases of our
   *   $allowInvalid parameter.
   */
  protected function getDiff(
    NodeInterface $node, bool $allowInvalid = false
  ): array {

    // Return a cached render array if one is found in the cache.
    if ($this->wikiNodeChangesCache->isCached($node, $allowInvalid)) {
      return $this->wikiNodeChangesCache->get($node, $allowInvalid);
    }

    /** \Drupal\omnipedia_core\Entity\NodeInterface|null */
    $previousNode = $node->getPreviousWikiNodeRevision();

    /** @var \Drupal\Core\Entity\EntityViewBuilderInterface */
    $viewBuilder = $this->entityTypeManager->getViewBuilder(
      $node->getEntityTypeId()
    );

    /** @var array */
    $previousRenderArray = $viewBuilder->view($previousNode, 'full');

    /** @var array */
    $currentRenderArray = $viewBuilder->view($node, 'full');

    // We need to create a new render context to render the previous and current
    // nodes in, so that we can capture the metadata (caching, attachments) and
    // store it in the cached copy. This also ensures that any metadata from
    // rendering these does not bubble up to the parent context, unless it
    // renders the render array that we return.
    /** @var \Drupal\Core\Render\RenderContext */
    $renderContext = new RenderContext();

    /** @var string */
    $previousRendered = (string) $this->renderer->executeInRenderContext(
      $renderContext, function() use (&$previousRenderArray) {
        return $this->renderer->render($previousRenderArray);
      }
    );

    /** @var string */
    $currentRendered = (string) $this->renderer->executeInRenderContext(
      $renderContext, function() use (&$currentRenderArray) {
        return $this->renderer->render($currentRenderArray);
      }
    );

    /** @var \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostRenderPreBuildEvent */
    $postRenderEvent = new DiffPostRenderPreBuildEvent(
      $node, $previousNode, $currentRendered, $previousRendered
    );

    // Dispatch the event with the event object.
    $this->eventDispatcher->dispatch(
      OmnipediaContentChangesEventInterface::DIFF_POST_RENDER_PRE_BUILD,
      $postRenderEvent
    );

    $this->htmlDiff->setOldHtml($postRenderEvent->getPreviousRendered());
    $this->htmlDiff->setNewHtml($postRenderEvent->getCurrentRendered());

    // Build and merge the metadata for both nodes. This allows us to store the
    // cache metadata and attachments for both, so that they can be retrieved
    // along with the rendered diff and passed to Drupal's render system.
    /** @var \Drupal\Core\Render\BubbleableMetadata */
    $bubbleableMetadata = (BubbleableMetadata::createFromRenderArray(
      $previousRenderArray
    ))->merge(
      BubbleableMetadata::createFromRenderArray($currentRenderArray)
    );

    // Disable PHP libxml errors because we sometimes end up with invalid
    // nesting, e.g. <figure> inside of <dl> elements.
    //
    // @see https://stackoverflow.com/questions/6090667/php-domdocument-errors-warnings-on-html5-tags#6090728
    \libxml_use_internal_errors(true);

    $this->htmlDiff->build();

    \libxml_use_internal_errors(false);

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $differenceCrawler = (new Crawler(
      '<div id="omnipedia-changes-root">' .
        $this->htmlDiff->getDifference() .
      '</div>'
    ))->filter('#omnipedia-changes-root');

    // Removes the node title element that Drupal generates.
    //
    // @todo Can this be handled in a node entity view mode instead?
    foreach ($differenceCrawler->filter('.node__title') as $element) {
      $element->parentNode->removeChild($element);
    }

    /** @var \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent */
    $postBuildEvent = new DiffPostBuildEvent(
      $node, $previousNode, $currentRendered, $previousRendered,
      $differenceCrawler
    );

    // Dispatch the event with the event object.
    $this->eventDispatcher->dispatch(
      OmnipediaContentChangesEventInterface::DIFF_POST_BUILD, $postBuildEvent
    );

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $differenceCrawler = $postBuildEvent->getCrawler();

    /** @var array */
    $renderArray = [
      '#markup'   => $differenceCrawler->html(),

      // Since the parsed diffs have already been run through the renderer and
      // filtering system, we're setting #printed to true to avoid Drupal
      // filtering the output a second time and breaking stuff. For example,
      // this would remove style attributes and strip SVG icons.
      '#printed'  => true,
    ];

    // Apply the metadata to the render array.
    $bubbleableMetadata->applyTo($renderArray);

    // Save the rendered diff to cache.
    $this->wikiNodeChangesCache->set($node, $renderArray);

    return $renderArray;

  }

  /**
   * {@inheritdoc}
   *
   * @see $this->getDiff()
   *   Gets diff content for a wiki node.
   */
  public function build(
    NodeInterface $node, bool $allowInvalid = false
  ): array {

    /** \Drupal\omnipedia_core\Entity\NodeInterface|null */
    $previousNode = $node->getPreviousWikiNodeRevision();

    // Bail if not a wiki node or the wiki node does not have a previous
    // revision.
    if (!\is_object($previousNode)) {
      return [];
    }

    /** @var array */
    $renderArray = $this->getDiff($node, $allowInvalid);

    $renderArray['#markup'] =
      // Note that we can't use '#type' => 'container' or some other wrapper
      // while also setting '#printed' => true as we've told Drupal to do no
      // further rendering.
      //
      // @todo Rework this as a Twig template?
      '<div class="' . $this->getChangesBaseClass() . '">' .
        $renderArray['#markup'] .
      '</div>';

    $renderArray['#attached']['library'][] =
      'omnipedia_changes/component.changes';

    return $renderArray;

  }

  /**
   * {@inheritdoc}
   */
  public function buildPlaceholder(NodeInterface $node): array {

    return [
      '#markup' => $this->t(
        'The changes for this date are still being built. Please check back in a few minutes.'
      ),
      '#cache'  => $this->wikiNodeChangesInfo->getPlaceholderCacheMetadata(
        $node->nid->getString()
      ),
    ];

  }

}
