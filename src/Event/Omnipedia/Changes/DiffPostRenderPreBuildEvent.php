<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Event\Omnipedia\Changes;

use Drupal\omnipedia_changes\Event\Omnipedia\Changes\AbstractDiffEvent;

/**
 * Omnipedia changes diff post-render pre-build event.
 *
 * This event is dispatched after the current and previous wiki node revisions
 * have been rendered, and before the diff is built, allowing alterations to
 * the markup sent to the diffing service.
 */
class DiffPostRenderPreBuildEvent extends AbstractDiffEvent {
}
