This contains the source files for the "*Omnipedia - Changes*" Drupal module,
which provides the wiki page changes functionality for
[Omnipedia](https://omnipedia.app/).

⚠️ ***[Why open source? / Spoiler warning](https://omnipedia.app/open-source)***

----

# Description

This contains our infrastructure for generating the wiki page changes between
two in-universe dates. We realized early on in development that the sheer amount
of content that editors would have to deal with would become a nightmare to
manage and keep track of if they also had to manually mark up the changes, and
this problem would only become exponentially worse the more content and dates
were added. A completely automated solution to generate these changes was a
must.

## Under the hood

One major problem when dealing with trying to generate a diff between two
strings that are HTML is that a lot of libraries out there don't actually
understand HTML elements and would mangle the HTML structure in expected and
unexpected ways. We could render the HTML as plain text, without the HTML
elements, but that would require somehow reconstructing the HTML on top of a
diff, which did not seem remotely practical. What we needed was a library that
understands HTML and where elements start and end. After a bit of research, we
looked into what [the Diff module](https://www.drupal.org/project/diff) uses,
and found our solution: the [`caxy/php-htmldiff`
library](https://github.com/caxy/php-htmldiff).

After solving that initial problem, we needed to customize the output of the
library, but it didn't offer any useful way to do this before it rendered its
diffs. The solution we settled on, like many other things on Omnipedia, was to
parse the rendered diffed HTML into a
[DOM](https://developer.mozilla.org/en-US/docs/Web/API/Document_Object_Model)
and manipulate it using [the Symfony DomCrawler
component](https://symfony.com/doc/current/components/dom_crawler.html) and
[PHP's DOM](https://www.php.net/manual/en/book.dom.php) classes that it wraps.

The core code that orchestrates all of this is [the wiki changes builder
service](/src/Service/WikiNodeChangesBuilder.php), which configures the
`caxy/php-htmldiff` instance, validates that a wiki page can have a diff (i.e.
has a previous in-universe date to diff against), returns a cached copy if one
is found, or renders the actual diff and dispatches events both before the diff
is generated and after. Once that core system was in place, we wrote [several
event subscribers](/src/EventSubscriber/Omnipedia/Changes) to alter the output
to our requirements.

### Asynchronicity

It's at this point that we ran into a serious problem: while uncached changes
for most wiki pages would take a second or two to generate and be sent to the
browser, a few outliers would consistently take far longer, up to 30 seconds or
more. We were hitting the limits of what the library and PHP could handle, even
after [some excellent work by the library maintainer to improve
performance](https://github.com/caxy/php-htmldiff/issues/101).

The solution to this required significantly more engineering. The server was
fully capable of generating the wiki page changes, so what we had to do was to
render those changes independently of when they were requested; they would have
to be rendered ahead of time, asynchronously, in a separate process. [Drupal
core has a queue
system](https://api.drupal.org/api/drupal/core!core.api.php/group/queue) that
allows for batch processing, which [the Warmer
module](https://www.drupal.org/project/warmer) builds on top of to allow for
performing cache warming tasks.

[We wrote our own custom Warmer
plug-in](/src/Plugin/warmer/WikiNodeChangesWarmer.php) which is invoked via [a
cron job](https://en.wikipedia.org/wiki/Cron) that runs multiple times an hour.
The plug-in determines all possible variations a set of changes would need to be
rendered in, specifically different sets of user permissions, and then renders
them one by one. These are then cached to a [Permanent Cache
Bin](https://www.drupal.org/project/pcb) so that they survive any Drupal cache
clear that may be required when deploying updated code (though we try to
minimize cache clears).

### All together now

While all of this is happening in the background process, [the changes route
controller](/src/Controller/OmnipediaWikiNodeChangesController.php) was
rewritten to handle three possible states so that it always returns a fast
response to the browser:

1. If no changes have been built between the requested couple of dates, it will show a placeholder message telling the user to check back in a few minutes; it doesn't risk trying to build the changes and potentially make the user wait a long time to see them.

2. If changes have been built, but one of the two wiki pages was updated and thus the changes [cache item was invalidated](https://api.drupal.org/api/drupal/core!core.api.php/group/cache#delete) and the cron job hasn't run yet, it will show the old (invalidated) cache item.

3. If changes have been built and the cache item is valid, it will show that item.

----

# Requirements

* [Drupal 10 or 11](https://www.drupal.org/download)

* PHP 8.1

* [Composer](https://getcomposer.org/)

## Drupal dependencies

Before attempting to install this, you must add the Composer repositories as
described in the installation instructions for these dependencies:

* The [`ambientimpact_core` module](https://github.com/Ambient-Impact/drupal-ambientimpact-core).

* The following Omnipedia modules:

  * [`omnipedia_core`](https://gitlab.com/neurocracy/omnipedia/modules/omnipedia-core)

  * [`omnipedia_date`](https://gitlab.com/neurocracy/omnipedia/modules/omnipedia-date)

  * [`omnipedia_main_page`](https://gitlab.com/neurocracy/omnipedia/modules/omnipedia-main-page)

  * [`omnipedia_user`](https://gitlab.com/neurocracy/omnipedia/modules/omnipedia-user)

## Front-end dependencies

To build front-end assets for this project, [Node.js](https://nodejs.org/) and
[Yarn](https://yarnpkg.com/) are required.

----

# Installation

## Composer

### Set up

Ensure that you have your Drupal installation set up with the correct Composer
installer types such as those provided by [the `drupal/recommended-project`
template](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates#s-drupalrecommended-project).
If you're starting from scratch, simply requiring that template and following
[the Drupal.org Composer
documentation](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates)
should get you up and running.

### Repository

In your root `composer.json`, add the following to the `"repositories"` section:

```json
{
  "type": "vcs",
  "url": "https://gitlab.com/neurocracy/omnipedia/modules/omnipedia-changes.git",
  "only": ["drupal/omnipedia_changes"]
}
```

### Patching

This provides [one or more patches](#patches). These can be applied automatically by the the
[`cweagans/composer-patches`](https://github.com/cweagans/composer-patches/tree/1.x)
Composer plug-in, but some set up is required before installing this module.
Notably, you'll need to [enable patching from
dependencies](https://github.com/cweagans/composer-patches/tree/1.x#allowing-patches-to-be-applied-from-dependencies) (such as this module 🤓). At
a minimum, you should have these values in your root `composer.json` (merge with
existing keys as needed):

```json
{
  "require": {
    "cweagans/composer-patches": "^1.7.0"
  },
  "config": {
    "allow-plugins": {
      "cweagans/composer-patches": true
    }
  },
  "extra": {
    "enable-patching": true,
    "patchLevel": {
      "drupal/core": "-p2"
    }
  }
}

```

**Important**: The 1.x version of the plug-in is currently required because it
allows for applying patches from a dependency; this is not implemented nor
planned for the 2.x branch of the plug-in.

### Installing

Once you've completed all of the above, run `composer require
"drupal/omnipedia_changes:^7.0@dev"` in the root of your project to have
Composer install this and its required dependencies for you.

## Front-end assets

To build front-end assets for this project, you'll need to install
[Node.js](https://nodejs.org/) and [Yarn](https://yarnpkg.com/).

This package makes use of [Yarn
Workspaces](https://yarnpkg.com/features/workspaces) and references other local
workspace dependencies. In the `package.json` in the root of your Drupal
project, you'll need to add the following:

```json
"workspaces": [
  "<web directory>/modules/custom/*"
],
```

where `<web directory>` is your public Drupal directory name, `web` by default.
Once those are defined, add the following to the `"dependencies"` section of
your top-level `package.json`:

```json
"drupal-omnipedia-changes": "workspace:^7"
```

Then run `yarn install` and let Yarn do the rest.

### Optional: install yarn.BUILD

While not required, [yarn.BUILD](https://yarn.build/) is recommended to make
building all of the front-end assets even easier.

----

# Building front-end assets

This uses [Webpack](https://webpack.js.org/) and [Symfony Webpack
Encore](https://symfony.com/doc/current/frontend.html) to automate most of the
build process. These will have been installed for you if you followed the Yarn
installation instructions above.

If you have [yarn.BUILD](https://yarn.build/) installed, you can run:

```
yarn build
```

from the root of your Drupal site. If you want to build just this package, run:

```
yarn workspace drupal-omnipedia-changes run build
```

----

# Major breaking changes

The following major version bumps indicate breaking changes:

* 4.x - Front-end package manager is now [Yarn](https://yarnpkg.com/); front-end build process ported to [Webpack](https://webpack.js.org/).

* 5.x - Moved and refactored the `omnipedia.wiki_node_changes_user` service to multiple services in the [`omnipedia_user` module](https://gitlab.com/neurocracy/omnipedia/modules/omnipedia-user).

* 6.x - Requires Drupal 9.5; includes backward compatible [Drupal 10](https://www.drupal.org/project/drupal/releases/10.0.0) deprecation fixes but is still not fully compatible.

* 7.x:

  * Requires [Drupal 10](https://www.drupal.org/project/drupal/releases/10.0.0) due to non-backwards compatible change to [`\Drupal\Component\EventDispatcher\ContainerAwareEventDispatcher::dispatch()`](https://git.drupalcode.org/project/drupal/-/commit/7b324dd8f18919fc4d728bdb0afbcf27c8c02cb2#6e9d627c11801448b7a793c204471d8f951ae2fb).

  * Requires [`ambientimpact_core`](https://github.com/Ambient-Impact/drupal-ambientimpact-core) 2.x due to the event dispatcher change.
