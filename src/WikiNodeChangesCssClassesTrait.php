<?php

namespace Drupal\omnipedia_changes;

/**
 * Wiki node changes CSS classes trait.
 *
 * This provides methods to retrieve all BEM-style CSS classes used in the wiki
 * node changes output.
 *
 * @see \Drupal\omnipedia_changes\WikiNodeChangesCssClassesInterface
 *   Intended to be used with this interface.
 */
trait WikiNodeChangesCssClassesTrait {

  /**
   * {@inheritdoc}
   */
  public function getChangesBaseClass(): string {
    return 'omnipedia-changes';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffElementClass(): string {
    return $this->getChangesBaseClass() . '__diff';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffAddedModifierClass(): string {
    return $this->getDiffElementClass() . '--added';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffRemovedModifierClass(): string {
    return $this->getDiffElementClass() . '--removed';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffChangedModifierClass(): string {
    return $this->getDiffElementClass() . '--changed';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffChangedAddedElementClass(): string {
    return $this->getDiffElementClass() . '-changed-added';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffChangedRemovedElementClass(): string {
    return $this->getDiffElementClass() . '-changed-removed';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffLinkElementClass(): string {
    return $this->getDiffElementClass() . '-link';
  }

  /**
   * {@inheritdoc}
   */
  public function getDiffLinkChangedModifierClass(): string {
    return $this->getDiffLinkElementClass() . '--changed';
  }

}
