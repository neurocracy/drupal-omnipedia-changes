This contains the source files for the "*Omnipedia - Changes*" Drupal module,
which provides the wiki page changes functionality for
[Omnipedia](https://omnipedia.app/).

⚠️⚠️⚠️ ***Here be potential spoilers. Proceed at your own risk.*** ⚠️⚠️⚠️

----

# Why open source?

We're dismayed by how much knowledge and technology is kept under lock and key
in the videogame industry, with years of work often never seeing the light of
day when projects are cancelled. We've gotten to where we are by building upon
the work of countless others, and we want to keep that going. We hope that some
part of this codebase is useful or will inspire someone out there.

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

# Planned improvements

* [Refactor changes rendering to generate temporary user accounts instead of using existing accounts](https://github.com/neurocracy/drupal-omnipedia-changes/issues/1)

* [Refactor `OmnipediaWikiNodeChangesController` to no longer extend `ControllerBase`](https://github.com/neurocracy/drupal-omnipedia-changes/issues/2)

----

# Requirements

* [Drupal 9](https://www.drupal.org/download) ([Drupal 8 is end-of-life](https://www.drupal.org/psa-2021-11-30))

* PHP 8

* [Composer](https://getcomposer.org/)

## Drupal dependencies

* The [```ambientimpact_core``` module](https://github.com/Ambient-Impact/drupal-modules) must be present.

* The [`drupal/omnipedia_core`](https://github.com/neurocracy/drupal-omnipedia-core) and [`drupal/omnipedia_date`](https://github.com/neurocracy/drupal-omnipedia-date) modules must be present.

## Front-end dependencies

To build front-end assets for this project, [Node.js](https://nodejs.org/) and
[Yarn](https://yarnpkg.com/) are required.

----

# Installation

## Composer

Ensure that you have your Drupal installation set up with the correct Composer
installer types such as those provided by [the ```drupal\recommended-project```
template](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates#s-drupalrecommended-project).
If you're starting from scratch, simply requiring that template and following
[the Drupal.org Composer
documentation](https://www.drupal.org/docs/develop/using-composer/starting-a-site-using-drupal-composer-project-templates)
should get you up and running.

Then, in your root ```composer.json```, add the following to the
```"repositories"``` section:

```json
"drupal/omnipedia_changes": {
  "type": "vcs",
  "url": "https://github.com/neurocracy/drupal-omnipedia-changes.git"
}
```

Then, in your project's root, run ```composer require
"drupal/omnipedia_changes:4.x-dev@dev"``` to have Composer install the module
and its required dependencies for you.

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
"drupal-omnipedia-changes": "workspace:^4"
```

Then run `yarn install` and let Yarn do the rest.

### Optional: install yarn.BUILD

While not required, we recommend installing [yarn.BUILD](https://yarn.build/) to
make building all of the front-end assets even easier.

### Optional: use ```nvm```

If you want to be sure you're using the same Node.js version we're using, we
support using [Node Version Manager (```nvm```)](https://github.com/nvm-sh/nvm)
([Windows port](https://github.com/coreybutler/nvm-windows)). Once ```nvm``` is
installed, you can simply navigate to the project root and run ```nvm install```
to install the appropriate version contained in the ```.nvmrc``` file.

Note that if you're using the [Windows
port](https://github.com/coreybutler/nvm-windows), it [does not support
```.nvmrc```
files](https://github.com/coreybutler/nvm-windows/wiki/Common-Issues#why-isnt-nvmrc-supported-why-arent-some-nvm-for-macoslinux-features-supported),
so you'll have to provide the version contained in the ```.nvmrc``` as a
parameter: ```nvm install <version>``` (without the ```<``` and ```>```).

This step is not required, and may be dropped in the future as Node.js is fairly
mature and stable at this point.

----

# Building front-end assets

We use [Webpack](https://webpack.js.org/) and [Symfony Webpack
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
