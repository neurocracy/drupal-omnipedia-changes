<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\EventSubscriber\Omnipedia\Changes;

use Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent;
use Drupal\omnipedia_changes\Event\OmnipediaContentChangesEventInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to alter any added wiki node changes diff content.
 */
class DiffAlterAddedContentEventSubscriber implements EventSubscriberInterface, WikiNodeChangesCssClassesInterface {

  use WikiNodeChangesCssClassesTrait;

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OmnipediaContentChangesEventInterface::DIFF_POST_BUILD => 'onDiffPostBuild',
    ];
  }

  /**
   * Alter any added content found in the provided DOM.
   *
   * The following alterations are made:
   *
   * - The default classes are removed from <ins> elements and our own BEM
   *   classes are added. This handles diffed list items as well as standalone
   *   <ins> elements.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   *
   * @todo Should the list item <ins> selectors attempt to avoid potential
   *   nesting?
   */
  protected function alterAddedContent(DiffPostBuildEvent $event): void {

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $crawler = $event->getCrawler();

    /** @var string */
    $changedInsClass = $this->getDiffChangedAddedElementClass();

    foreach ($crawler->filter(\implode(',', [
      'ins.diffins',
      // This catches any changed <ins> that aren't handled by the changed
      // event subscriber.
      'ins.diffmod',
      '.diff-list > .replacement ins:not(.' . $changedInsClass . ')',
      '.diff-list > .new ins:not(.' . $changedInsClass . ')',
    ])) as $insElement) {
      $insElement->setAttribute('class', \implode(' ', [
        $this->getDiffElementClass(),
        $this->getDiffAddedModifierClass(),
      ]));
    }

  }

  /**
   * DiffPostBuildEvent handler.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   */
  public function onDiffPostBuild(DiffPostBuildEvent $event): void {

    $this->alterAddedContent($event);

  }

}
