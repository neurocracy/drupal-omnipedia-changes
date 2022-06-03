<?php

namespace Drupal\omnipedia_changes;

/**
 * Wiki node changes CSS classes interface.
 *
 * @see \Drupal\omnipedia_changes\WikiNodeChangesCssClassesTrait
 *   Intended to be used with this trait.
 */
interface WikiNodeChangesCssClassesInterface {

  /**
   * Get the base class used to generate HTML/CSS BEM classes.
   *
   * @return string
   */
  public function getChangesBaseClass(): string;

  /**
   * Get the BEM class for all diff elements.
   *
   * @return string
   */
  public function getDiffElementClass(): string;

  /**
   * Get the BEM modifier class for added diff elements.
   *
   * @return string
   */
  public function getDiffAddedModifierClass(): string;

  /**
   * Get the BEM modifier class for removed diff elements.
   *
   * @return string
   */
  public function getDiffRemovedModifierClass(): string;

  /**
   * Get the BEM modifier class for changed (added and removed) diff elements.
   *
   * @return string
   */
  public function getDiffChangedModifierClass(): string;

  /**
   * Get the BEM class for added elements in changed content.
   *
   * @return string
   */
  public function getDiffChangedAddedElementClass(): string;

  /**
   * Get the BEM class for added elements in changed content.
   *
   * @return string
   */
  public function getDiffChangedRemovedElementClass(): string;

  /**
   * Get the BEM class for link elements.
   *
   * @return string
   */
  public function getDiffLinkElementClass(): string;

  /**
   * Get the BEM modifier class for changed link elements.
   *
   * @return string
   */
  public function getDiffLinkChangedModifierClass(): string;

}
