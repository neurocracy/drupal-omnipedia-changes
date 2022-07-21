<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Event;

/**
 * Interface OmnipediaContentChangesEventInterface.
 */
interface OmnipediaContentChangesEventInterface {

  /**
   * Alter diff content after the nodes have been rendered.
   *
   * This event is dispatched after the current and previous wiki node revisions
   * have been rendered, and before the diff is built, allowing alterations to
   * the markup sent to the diffing service.
   *
   * @Event
   *
   * @var string
   */
  public const DIFF_POST_RENDER_PRE_BUILD = 'omnipedia.content_changes.diff_post_render_pre_build';

  /**
   * Alter diff content after it's built.
   *
   * @Event
   *
   * @var string
   */
  public const DIFF_POST_BUILD = 'omnipedia.content_changes.diff_post_build';

}
