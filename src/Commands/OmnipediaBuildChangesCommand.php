<?php

declare(strict_types=1);

namespace Drupal\omnipedia_changes\Commands;

use Consolidation\SiteAlias\SiteAliasManagerAwareInterface;
use Consolidation\SiteAlias\SiteAliasManagerAwareTrait;
use Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface;
use Drush\Commands\DrushCommands;

/**
 * Omnipedia wiki node build changes Drush command.
 */
class OmnipediaBuildChangesCommand extends DrushCommands implements SiteAliasManagerAwareInterface {

  use SiteAliasManagerAwareTrait;

  /**
   * The warmer ID we're passing to the warmer:enqueue command.
   */
  protected const WARMER_ID = 'omnipedia_wiki_node_changes';

  /**
   * The Omnipedia wiki node changes cache service.
   *
   * @var \Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface
   */
  protected $wikiNodeChangesCache;

  /**
   * Constructs this command; saves dependencies.
   *
   * @param \Drupal\omnipedia_changes\Service\WikiNodeChangesCacheInterface $wikiNodeChangesCache
   *   The Omnipedia wiki node changes cache service.
   */
  public function __construct(
    WikiNodeChangesCacheInterface $wikiNodeChangesCache
  ) {

    $this->wikiNodeChangesCache = $wikiNodeChangesCache;

  }

  /**
   * Build Omnipedia wiki node changes.
   *
   * @command omnipedia:changes-build
   *
   * @option rebuild
   *   Rebuild already cached changes. This will invalidate the entire changes
   *   cache before building.
   *
   * @usage omnipedia:changes-build
   *   Build wiki node changes that haven't been built/cached.
   *
   * @usage omnipedia:changes-build --rebuild
   *   Rebuild all wiki node changes, including those already built/cached.
   *
   * @aliases omnipedia:cb
   */
  public function buildChanges(array $options = [
    'rebuild' => false,
  ]) {

    // If told to rebuild, invalidate all items in the changes cache.
    if ($options['rebuild'] === true) {
      $this->wikiNodeChangesCache->getCacheBin()->invalidateAll();
    }

    /** @var array Options to pass to the warmer:enqueue command. */
    $warmerOptions = [
      'run-queue' => true,
      'yes'       => true,
    ];

    // Pass on the 'debug' and 'verbose' options to the warmer:enqueue command.
    foreach (['debug', 'verbose'] as $key) {
      if (isset($options[$key])) {
        $warmerOptions[$key] = $options[$key];
      }
    }

    /** @var \Consolidation\SiteProcess\SiteProcess */
    $process = $this->processManager()->drush(
      $this->siteAliasManager()->getSelf(),
      'warmer:enqueue',
      [self::WARMER_ID],
      $warmerOptions
    );

    // Run the process and output the process' progress in realtime.
    $process->mustRun($process->showRealtime());

  }

}
