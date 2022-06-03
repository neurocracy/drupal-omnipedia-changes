<?php

namespace Drupal\omnipedia_changes\EventSubscriber\Omnipedia\Changes;

use Drupal\ambientimpact_core\Utility\Html;
use Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent;
use Drupal\omnipedia_changes\Event\OmnipediaContentChangesEventInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesInterface;
use Drupal\omnipedia_changes\WikiNodeChangesCssClassesTrait;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to alter any changed wiki node changes diff content.
 */
class DiffAlterChangedContentEventSubscriber implements EventSubscriberInterface, WikiNodeChangesCssClassesInterface {

  use WikiNodeChangesCssClassesTrait;

  /**
   * Element names that we won't include in a changed wrapper.
   */
  protected const IGNORE_ELEMENT_NAMES = [
    'p', 'h1', 'h2', 'h3', 'h4', 'h5', 'h6',
    'div', 'picture', 'source', 'img', 'article',
  ];

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OmnipediaContentChangesEventInterface::DIFF_POST_BUILD => 'onDiffPostBuild',
    ];
  }

  /**
   * Determine if the provided DOM node is considered a valid previous sibling.
   *
   * @param \DOMNode|null $node
   *   The DOM node to check, or null.
   *
   * @return boolean
   *   Returns true if $node is null, if $node is a \DOMNode but not a
   *   \DOMElement, or if $node is a \DOMElement but does not have the .diffmod
   *   class. Returns false otherwise.
   */
  protected function isValidPreviousSibling(?\DOMNode $node): bool {

    if (
      $node === null ||
      !($node instanceof \DOMElement) ||
      !Html::elementHasClass($node, 'diffmod')/* ||
      !\in_array($node->nodeName, self::IGNORE_ELEMENT_NAMES)*/
    ) {
      return true;
    }

    return false;

  }

  /**
   * Determine if the provided DOM node is considered a valid next sibling.
   *
   * @param \DOMNode|null $node
   *   The DOM node to check, or null.
   *
   * @return boolean
   *   Returns true if $node an object (not null), if $node is a \DOMElement,
   *   and if $node has the .diffmod class. Returns false otherwise.
   */
  protected function isValidNextSibling(?\DOMNode $node): bool {

    if (
      \is_object($node) &&
      $node instanceof \DOMElement &&
      Html::elementHasClass($node, 'diffmod')/* &&
      !\in_array($node->nodeName, self::IGNORE_ELEMENT_NAMES)*/
    ) {
      return true;
    }

    return false;

  }

  /**
   * Find DOM elements which are the starting .diffmod in a chain.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $crawler
   *   A Symfony DomCrawler instance to search.
   *
   * @return \Symfony\Component\DomCrawler\Crawler
   *   A Symfony DomCrawler instance containing zero or more starting elements.
   */
  protected function findStartingElements(Crawler $crawler): Crawler {

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $startingElementsCrawler = new Crawler();

    foreach ($crawler->filter('.diffmod') as $element) {

      // Ignore certain element types.
      if (\in_array($element->nodeName, self::IGNORE_ELEMENT_NAMES)) {
        continue;
      }

      /** @var \DOMNode|null */
      $previous = $element->previousSibling;

      /** @var \DOMNode|null */
      $next = $element->nextSibling;

      if (
        $this->isValidPreviousSibling($previous) &&
        $this->isValidNextSibling($next)
      ) {
        $startingElementsCrawler->addNode($element);
      }

    }

    return $startingElementsCrawler;

  }

  /**
   * Build a group from a provided starting DOM element.
   *
   * @param \DOMElement $element
   *   The starting DOM element in a chain.
   *
   * @return \Symfony\Component\DomCrawler\Crawler
   *   A Symfony DomCrawler instance containing the starting element and any
   *   valid siblings that follow it.
   */
  protected function buildGroupFromStartingElement(
    \DOMElement $element
  ): Crawler {

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $groupCrawler = new Crawler($element);

    $sibling = $element->nextSibling;

    while (\is_object($sibling) && $sibling instanceof \DOMElement) {

      if (!Html::elementHasClass($sibling, 'diffmod')) {
        break;
      }

      $groupCrawler->add($sibling);

      $sibling = $sibling->nextSibling;

    }

    return $groupCrawler;

  }

  /**
   * Wrap a provided group in a container.
   *
   * @param \Symfony\Component\DomCrawler\Crawler $groupCrawler
   *   A Symfony DomCrawler instance containing the group, in the order they
   *   appear in the DOM.
   *
   * @return boolean
   *   True if the container was successfully created and false otherwise.
   */
  protected function wrapGroup(Crawler $groupCrawler): bool {

    /** @var \DOMElement The starting element. This is always the first node in the crawler. */
    $startingElement = $groupCrawler->getNode(0);

    /** @var \DOMElement|false */
    $changedContainer = $startingElement->ownerDocument->createElement('span');

    if (!$changedContainer) {
      return false;
    }

    $changedContainer->setAttribute('class', \implode(' ', [
      $this->getDiffElementClass(),
      $this->getDiffChangedModifierClass(),
    ]));

    // Insert the wrapper before the starting element.
    $startingElement->parentNode->insertBefore(
      $changedContainer, $startingElement
    );

    foreach ($groupCrawler as $groupElement) {
      $changedContainer->appendChild($groupElement);
    }

    return true;

  }

  /**
   * Group deleted and inserted content.
   *
   * This searches for any .diffmod elements that are immediate siblings - i.e.
   * without any other elements or text nodes between them - and groups them
   * in a container for easier styling.
   *
   * The following alterations are made on <del> and <ins> elements found via
   * the 'del.diffmod + ins.diffmod' selector:
   *
   * - Both the <del> and <ins> elements are wrapped in a changes container
   *   <span> for styling.
   *
   * - The 'diffmod' class is removed from <ins> and <del> elements and our own
   *   BEM classes are added.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   */
  protected function groupChangedNodes(DiffPostBuildEvent $event): void {

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $crawler = $event->getCrawler();

    /** @var \Symfony\Component\DomCrawler\Crawler */
    $startingElements = $this->findStartingElements($crawler);

    foreach ($startingElements as $element) {

      /** @var \Symfony\Component\DomCrawler\Crawler */
      $groupCrawler = $this->buildGroupFromStartingElement($element);

      /** @var \Symfony\Component\DomCrawler\Crawler */
      $insCrawler = $groupCrawler->filter('ins');

      /** @var \Symfony\Component\DomCrawler\Crawler */
      $delCrawler = $groupCrawler->filter('del');

      // If there are only <ins> elements, replace their .diffmod class with
      // .diffins and skip to the next group. This handles certain edge cases
      // produced by the HTML diff service.
      if (count($insCrawler) > 0 && count($delCrawler) === 0) {

        foreach ($insCrawler as $insElement) {
          Html::setElementClassAttribute(
            $insElement,
            Html::getElementClassAttribute($insElement)
              ->removeClass('diffmod')->addClass('diffins')
          );
        }

        continue;

      // If there are only <del> elements, replace their .diffmod class with
      // .diffdel and skip to the next group. This handles certain edge cases
      // produced by the HTML diff service.
      } else if (count($insCrawler) === 0 && count($delCrawler) > 0) {

        foreach ($delCrawler as $delElement) {
          Html::setElementClassAttribute(
            $delElement,
            Html::getElementClassAttribute($delElement)
              ->removeClass('diffmod')->addClass('diffdel')
          );
        }

        continue;

      }

      // Attempt to wrap the group in a wrapper, moving to the next group if
      // that fails.
      if (!$this->wrapGroup($groupCrawler)) {
        continue;
      }

      foreach ([
        $this->getDiffChangedAddedElementClass()    => $insCrawler,
        $this->getDiffChangedRemovedElementClass()  => $delCrawler,
      ] as $className => $diffElementCrawler) {
        foreach ($diffElementCrawler as $diffElement) {

          $diffElement->setAttribute('class', $className);

        }
      }

    }

  }

  /**
   * DiffPostBuildEvent handler.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostBuildEvent $event
   *   The event object.
   */
  public function onDiffPostBuild(DiffPostBuildEvent $event): void {

    $this->groupChangedNodes($event);

  }

}
