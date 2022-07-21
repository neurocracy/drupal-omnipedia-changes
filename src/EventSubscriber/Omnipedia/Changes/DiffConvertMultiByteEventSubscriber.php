<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\EventSubscriber\Omnipedia\Changes;

use Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostRenderPreBuildEvent;
use Drupal\omnipedia_changes\Event\OmnipediaContentChangesEventInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;

/**
 * Event subscriber to convert non-Latin multi-byte characters to HTML entities.
 *
 * The caxy/php-htmldiff library will use PHP's multi-byte functions internally
 * if it detects that a string is encoded as such, and these functions are
 * slower than the non-multi-byte functions. This event subscriber converts any
 * non-Latin characters in the current and previous wiki node rendered output
 * from multi-byte to HTML entities, and the resulting strings to single-byte.
 *
 * Note that this is not currently in use as it doesn't seem to make any
 * noticeable difference, even when it's confirmed that the markup sent to the
 * HTML diff service is in ASCII (not UTF-8), and the following is true:
 *   \strlen() === \mb_strlen()
 *
 * @see https://github.com/caxy/php-htmldiff/issues/57
 * @see https://github.com/caxy/php-htmldiff/issues/77
 *
 * @see https://stackoverflow.com/questions/13280200/convert-unicode-to-html-entities-hex/13280706#13280706
 */
class DiffConvertMultiByteEventSubscriber implements EventSubscriberInterface {

  /**
   * {@inheritdoc}
   */
  public static function getSubscribedEvents(): array {
    return [
      OmnipediaContentChangesEventInterface::DIFF_POST_RENDER_PRE_BUILD => 'onDiffPostRenderPreBuild',
    ];
  }

  /**
   * Convert the current/previous wiki node markup multi-byte to HTML entities.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostRenderPreBuildEvent $event
   *   The event object.
   */
  protected function convertMultiByte(
    DiffPostRenderPreBuildEvent $event
  ): void {

    /** @var string[] */
    $rendered = [
      'current'   => $event->getCurrentRendered(),
      'previous'  => $event->getPreviousRendered(),
    ];

    foreach ($rendered as $key => $html) {
      $rendered[$key] = \mb_convert_encoding($html, 'HTML-ENTITIES', 'UTF-8');
    }

    $event->setCurrentRendered($rendered['current']);
    $event->setPreviousRendered($rendered['previous']);

  }


  /**
   * DiffPostRenderPreBuildEvent handler.
   *
   * @param \Drupal\omnipedia_changes\Event\Omnipedia\Changes\DiffPostRenderPreBuildEvent $event
   *   The event object.
   */
  public function onDiffPostRenderPreBuild(
    DiffPostRenderPreBuildEvent $event
  ): void {

    $this->convertMultiByte($event);

  }

}
