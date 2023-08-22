<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Hooks;

use Drupal\Component\Render\MarkupInterface;
use Drupal\Core\DependencyInjection\ContainerInjectionInterface;
use Drupal\Core\Routing\RouteMatchInterface;
use Drupal\Core\StringTranslation\StringTranslationTrait;
use Drupal\hux\Attribute\Hook;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Help hook implementations.
 */
class Help implements ContainerInjectionInterface {

  use StringTranslationTrait;

  /**
   * Base BEM class for the changes container.
   *
   * @var string
   */
  protected const BASE_CLASS = 'omnipedia-changes-help';

  /**
   * The changes library to attach to the render array.
   * @var string
   */
  protected const LIBRARY = 'omnipedia_changes/component.changes';

  /**
   * Hook constructor; saves dependencies.
   *
   * @param \Drupal\Core\StringTranslation\TranslationInterface $stringTranslation
   *   The Drupal string translation service.
   */
  public function __construct(protected $stringTranslation) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container) {
    return new static(
      $container->get('string_translation'),
    );
  }

  #[Hook('help')]
  /**
   * Implements \hook_help().
   *
   * @param string $routeName
   *   The current route name.
   *
   * @param \Drupal\Core\Routing\RouteMatchInterface $routeMatch
   *   The current route match.
   *
   * @return \Drupal\Component\Render\MarkupInterface|array|string
   */
  public function help(
    string $routeName, RouteMatchInterface $routeMatch,
  ): MarkupInterface|array|string {

    if ($routeName === 'entity.node.omnipedia_changes') {
      return $this->getChangesHelp();
    }

    return [];

  }

  /**
   * Get help content for the wiki node changes route.
   *
   * @return array
   *   A render array.
   */
  protected function getChangesHelp(): array {

    return ['omnipedia_changes_help' => [
      '#type'       => 'container',
      '#attributes' => ['class' => [self::BASE_CLASS]],

      'description' => [
        '#type'   => 'html_tag',
        '#tag'    => 'p',
        '#value'  => $this->t(
          'This displays additions, deletions, and changes to the current page compared to the previous version, using the following indicators:'
        ),
        '#attributes'   => ['class' => [self::BASE_CLASS . '__description']],
      ],

      'legend'  => [
        '#theme'        => 'item_list',
        '#list_type'    => 'ul',
        '#items'        => [
          'added' => [
            '#type'   => 'html_tag',
            '#tag'    => 'ins',
            '#value'  => $this->t('Added'),
            '#wrapper_attributes'   => ['class' => [
              self::BASE_CLASS . '__legend-item',
              self::BASE_CLASS . '__legend-item--added',
            ]],
            '#attributes'   => ['class' => [
              self::BASE_CLASS . '__diff',
              self::BASE_CLASS . '__diff--added',
            ]],
          ],
          'removed' => [
            '#type'   => 'html_tag',
            '#tag'    => 'del',
            '#value'  => $this->t('Deleted'),
            '#wrapper_attributes'   => ['class' => [
              self::BASE_CLASS . '__legend-item',
              self::BASE_CLASS . '__legend-item--removed',
            ]],
            '#attributes'   => ['class' => [
              self::BASE_CLASS . '__diff',
              self::BASE_CLASS . '__diff--removed',
            ]],
          ],
          'changed' => [
            '#type'   => 'html_tag',
            '#tag'    => 'span',
            '#value'  => $this->t('Changed'),
            '#wrapper_attributes'   => ['class' => [
              self::BASE_CLASS . '__legend-item',
              self::BASE_CLASS . '__legend-item--changed',
            ]],
            '#attributes'   => ['class' => [
              self::BASE_CLASS . '__diff',
              self::BASE_CLASS . '__diff--changed',
            ]],
          ],
        ],
        '#attributes' => ['class' => [
          self::BASE_CLASS . '__legend',
          'unlisted-list',
        ]],
      ],

      '#attached' => [
        'library' => [self::LIBRARY],
      ],
   ]];

  }

}
