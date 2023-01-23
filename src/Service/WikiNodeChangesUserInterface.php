<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\omnipedia_core\Entity\NodeInterface;
use Drupal\user\UserInterface;

/**
 * The Omnipedia wiki node changes user service interface.
 */
interface WikiNodeChangesUserInterface {

  /**
   * Get a user to render the provided wiki nodes' changes as.
   *
   * Since we need to generate multiple cache variations that vary per user
   * permissions, we need a representative user for each variation.
   * Unfortuntely, attempting to create temporary users (i.e. that have not been
   * saved to the database) has not been successful; that approach would have
   * been preferable to isolate/sandbox any potential security issues that could
   * arise with rendering as an existing user.
   *
   * Creating the temporary users via the user entity storage, assigning the
   * correct roles, and setting them as active is the easy part. However, many
   * places in Drupal core check User::isAuthenticated(), a method which returns
   * true only if the user ID (uid) is greater than 0; a user that has not yet
   * been saved to the database will always return false. Attempting to build a
   * solution that tricks Drupal into thinking a temporary user is authenticated
   * would likely be overengineering and could introduce unforeseen implications
   * for security.
   *
   * @param array $roles
   *   An array of role IDs (rids) to match to a user.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $node
   *   The current wiki node revision. Used for access checking.
   *
   * @param \Drupal\omnipedia_core\Entity\NodeInterface $previousNode
   *   The previous wiki node revision. Used for access checking.
   *
   * @return \Drupal\user\UserInterface|null
   *   Either a loaded user entity, or null if one can't be found that has only
   *   the provided $roles and has access to view both $node and $previousNode.
   */
  public function getUserToRenderAs(
    array $roles, NodeInterface $node, NodeInterface $previousNode
  ): ?UserInterface;

}
