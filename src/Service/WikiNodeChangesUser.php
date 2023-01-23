<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Service;

use Drupal\Core\Cache\Cache;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Session\AccountInterface;
use Drupal\omnipedia_changes\Service\WikiNodeChangesUserInterface;
use Drupal\omnipedia_core\Entity\NodeInterface;
use Drupal\omnipedia_user\Service\PermissionHashesInterface;
use Drupal\user\RoleStorageInterface;
use Drupal\user\UserInterface;
use Drupal\user\UserStorageInterface;

/**
 * The Omnipedia wiki node changes user service.
 */
class WikiNodeChangesUser implements WikiNodeChangesUserInterface {

  /**
   * All user role entities, keyed by role ID (rid).
   *
   * @var \Drupal\user\RoleInterface[]
   *
   * @see $this->getAllRoles()
   */
  protected array $allRoles;

  /**
   * The Omnipedia permission hashes service.
   *
   * @var \Drupal\omnipedia_user\Service\PermissionHashesInterface
   */
  protected PermissionHashesInterface $permissionHashes;

  /**
   * The Drupal user role entity storage.
   *
   * @var \Drupal\user\RoleStorageInterface
   */
  protected RoleStorageInterface $roleStorage;

  /**
   * The Drupal user entity storage.
   *
   * @var \Drupal\user\UserStorageInterface
   */
  protected UserStorageInterface $userStorage;

  /**
   * Service constructor; saves dependencies.
   *
   * @param \Drupal\Core\Entity\EntityTypeManagerInterface $entityTypeManager
   *   The Drupal entity type manager.
   *
   * @param \Drupal\omnipedia_user\Service\PermissionHashesInterface $permissionHashes
   *   The Omnipedia permission hashes service.
   */
  public function __construct(
    EntityTypeManagerInterface  $entityTypeManager,
    PermissionHashesInterface   $permissionHashes
  ) {

    $this->permissionHashes = $permissionHashes;
    $this->roleStorage = $entityTypeManager->getStorage('user_role');
    $this->userStorage = $entityTypeManager->getStorage('user');

  }

  /**
   * Get all roles.
   *
   * This returns all custom role entities, i.e. all but the 'anonymous' and
   * 'authenticated' roles as they're not actually stored in the user roles
   * data but inferred from the presence of a user's uid being greater than
   * zero, and as such would prevent a query matching any users with no custom
   * roles.
   *
   * @return \Drupal\user\RoleInterface[]
   *   All role entities, minus the 'anonymous' and 'authenticated' roles.
   *
   * @see $this->allRoles
   */
  protected function getAllRoles(): array {

    if (!isset($this->allRoles)) {

      /** @var \Drupal\user\RoleInterface[] */
      $this->allRoles = $this->roleStorage->loadMultiple();

      // Remove the 'anonymous' and 'authenticated' roles.
      foreach ([
        AccountInterface::ANONYMOUS_ROLE,
        AccountInterface::AUTHENTICATED_ROLE,
      ] as $removeRole) {
        if (isset($this->allRoles[$removeRole])) {
          unset($this->allRoles[$removeRole]);
        }
      }

    }

    return $this->allRoles;

  }

  /**
   * {@inheritdoc}
   *
   * Note that this doesn't necessarily use the most scalable method for
   * filtering users that only have the exact roles requested, as that's more
   * difficult than one might assume given how user roles assignments are
   * stored. The following have been attempted without success:
   *
   * - Looping through the provided roles and setting each one as query
   *   condition, i.e. @code $query->condition('roles', $role, '=') @endcode
   *
   * - Creating an OR or AND condition group and performing the above, i.e.
   *   @code $query->orConditionGroup() @endcode or
   *   @code $query->andConditionGroup() @endcode
   *
   * @todo Look at the Views user role filter SQL when choosing "Is all of" and
   *   setting multiple roles as a possible solution.
   *
   * @todo Determine if caching this is a good idea, or if Drupal's existing
   *   entity caching is fast enough to make that not worth the effort.
   *
   * @todo This needs tests to verify that it continues to work as expected,
   *   especially due to the potential security issues.
   *
   * @see https://drupal.stackexchange.com/questions/226396/perform-a-query-with-an-entity-field-condition-with-multiple-values/226410#226410
   *   Potential solution for matching all provided roles.
   *
   * @see https://drupal.stackexchange.com/questions/11175/get-all-users-with-specific-roles-using-entityfieldquery
   *   Drupal 7 question and answer on this problem.
   *
   * @see https://stackoverflow.com/questions/28939367/check-if-a-column-contains-all-the-values-of-another-column-mysql
   */
  public function getUserToRenderAs(
    array $roles, NodeInterface $node, NodeInterface $previousNode
  ): ?UserInterface {

    // If the anonymous user is requested, load and return it.
    if (
      count($roles) === 1 &&
      $roles[0] === AccountInterface::ANONYMOUS_ROLE
    ) {
      return $this->userStorage->load(0);
    }

    /** @var \Drupal\Core\Entity\Query\QueryInterface */
    $query = ($this->userStorage->getQuery())
      ->condition('status', 1);

    // If the provided roles are empty or the only role is 'authenticated, we
    // need to search for a user with only the 'authenticated' role and no
    // others, but we can't use the same conditions as when the user has one or
    // more custom roles, because that query will never match, so instead we set
    // the condition that the user does not have a roles entry.
    if (
      empty($roles) ||
      count($roles) === 1 &&
      \in_array(AccountInterface::AUTHENTICATED_ROLE, $roles)
    ) {

      $query->notExists('roles');

    // Otherwise, if the provided roles are not empty, add conditions both to
    // find a user with any of the provided roles and none of the remaining
    // roles, i.e. the inverse.
    } else {

      $query
        // This only searches for users that have at least one of the provided
        // roles. We filter out users not having all the roles after loading
        // each one to test for that.
        ->condition('roles', $roles, 'IN')
        // This works as expected to exclude users that don't have any of the
        // excluded roles.
        ->condition(
          'roles',
          \array_diff(\array_keys($this->getAllRoles()), $roles),
          'NOT IN'
        );

    }

    foreach ($query->execute() as $uid) {

      /** @var \Drupal\user\UserInterface */
      $user = $this->userStorage->load($uid);

      // Loop through the required roles and skip this user if they don't have
      // all of them. This is not
      foreach ($roles as $role) {
        if (!$user->hasRole($role)) {
          continue 2;
        }
      }

      // Return the first user found that has access to both the current and
      // previous wiki nodes.
      if (
        $node->access('view', $user) &&
        $previousNode->access('view', $user)
      ) {
        return $user;
      }

    }

    // If no user was found and returned, return null to indicate that.
    return null;

  }

}
