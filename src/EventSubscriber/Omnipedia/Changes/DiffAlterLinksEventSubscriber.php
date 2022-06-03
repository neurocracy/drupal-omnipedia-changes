<?php

namespace Drupal\omnipedia_changes\EventSubscriber\Omnipedia\Changes;

use Drupal\ambientimpact_core\Utility\Html;
use Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent;
use Drupal\omnipedia_changes\Event\OmnipediaContentChangesEventInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesTrait;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to alter any links in the wiki node changes diff content.
 */
class DiffAlterLinksEventSubscriber implements EventSubscriberInterface, WikiNodeChangesCssClassesInterface {

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
   * Alter any links found in the provided DOM.
   *
   * This removes the .diffmod class from links and adds our own BEM classes.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   */
  protected function alterLinks(DiffPostBuildEvent $event): void {

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $crawler = $event->getCrawler();

    foreach ($crawler->filter(\implode(',', [
      'a.diffmod',
      '.diffmod a',
      'ins a',
      'del a',
      '.diff-list li.new a',
      '.diff-list li.removed a',
      '.diff-list li.replacement a',
    ])) as $linkElement) {

      Html::setElementClassAttribute(
        $linkElement,
        Html::getElementClassAttribute($linkElement)
          ->removeClass('diffmod')
            ->addClass($this->getDiffLinkElementClass())
            ->addClass($this->getDiffLinkChangedModifierClass())
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

    $this->alterLinks($event);

  }

}
