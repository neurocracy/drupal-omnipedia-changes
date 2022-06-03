<?php

namespace Drupal\omnipedia_changes\EventSubscriber\Omnipedia\Changes;

use Drupal\ambientimpact_core\Utility\Html;
use Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent;
use Drupal\omnipedia_changes\Event\OmnipediaContentChangesEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to clean up any unaltered wiki node changes diff content.
 */
class DiffCleanUpEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OmnipediaContentChangesEventInterface::DIFF_POST_BUILD => 'onDiffPostBuild',
    ];
  }

  /**
   * Removes any remaining unaltered or invalid elements from the DOM.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   */
  protected function removeRemainingElements(DiffPostBuildEvent $event): void {

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $crawler = $event->getCrawler();

    foreach ($crawler->filter(\implode(',', [
      'ins.mod',
      'del.mod',
      // These cause layout issues and are likely invalid nesting.
      'picture ins',
      'picture del',
    ])) as $element) {

      Html::unwrapNode($element);

    }

  }

  /**
   * Remove any unaltered element classes from the DOM.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   */
  protected function removeRemainingClasses(DiffPostBuildEvent $event): void {

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $crawler = $event->getCrawler();

    foreach ($crawler->filter('.diffmod') as $element) {

      Html::setElementClassAttribute(
        $element,
        Html::getElementClassAttribute($element)->removeClass('diffmod')
      );

    }

  }

  /**
   * DiffPostBuildEvent handler.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   */
  public function onDiffPostBuild(DiffPostBuildEvent $event): void {

    $this->removeRemainingElements($event);

    $this->removeRemainingClasses($event);

  }

}
