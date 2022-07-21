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

# Requirements

* [Drupal 9](https://www.drupal.org/download) ([Drupal 8 is end-of-life](https://www.drupal.org/psa-2021-11-30))

* PHP 8

* [Composer](https://getcomposer.org/)

## Drupal dependencies

* The [```ambientimpact_core``` module](https://github.com/Ambient-Impact/drupal-modules) must be present.

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

```
{
  "type": "vcs",
  "url": "https://github.com/neurocracy/drupal-omnipedia-changes.git"
}
```

Then, in your project's root, run ```composer require
"drupal/omnipedia_changes:3.x-dev@dev"``` to have Composer install the module
and its required dependencies for you.

## Building assets

To build assets for this project, you'll need to have
[Node.js](https://nodejs.org/) installed.

### Using ```nvm```

We recommend using [Node Version Manager
(```nvm```)](https://github.com/nvm-sh/nvm) ([Windows
port](https://github.com/coreybutler/nvm-windows)) to ensure you're using the
same version used to develop this codebase. Once ```nvm``` is installed, you can
simply navigate to the project root and run ```nvm install``` to install the
appropriate version contained in the ```.nvmrc``` file.

Note that if you're using the [Windows
port](https://github.com/coreybutler/nvm-windows), it [does not support
```.nvmrc```
files](https://github.com/coreybutler/nvm-windows/wiki/Common-Issues#why-isnt-nvmrc-supported-why-arent-some-nvm-for-macoslinux-features-supported),
so you'll have to provide the version contained in the ```.nvmrc``` as a
parameter: ```nvm install <version>``` (without the ```<``` and ```>```).

### Dependencies

Once Node.js is installed, run ```npm install``` in the project root to install
all dependencies.

### Grunt CLI

We also recommend installing the [Grunt
CLI](https://gruntjs.com/getting-started) globally from the commandline:
```npm install -g grunt-cli```

Note that if you use ```nvm```, this must be done for each Node.js version that
you plan to use it for.

# Building

To build everything, you can run ```grunt all``` in the commandline in the
project root.

To build specific things:

* ```grunt css``` - compiles CSS files from Sass; applies [Autoprefixer](https://github.com/postcss/autoprefixer).

----

# Description

This contains our infrastructure for generating the wiki page changes between
two in-universe dates. This is process is completely automated, and is built on
top of the [`caxy/php-htmldiff` library](https://github.com/caxy/php-htmldiff),
with [many alterations](/src/EventSubscriber/Omnipedia/Changes) made to its
output. The actual changes [are built asynchronously in a separate
process](/src/Plugin/warmer/WikiNodeChangesWarmer.php), running as cron job
implemented as a [Warmer module](https://www.drupal.org/project/warmer) plug-in,
and cached to a [Permanent Cache Bin](https://www.drupal.org/project/pcb) so
that it survives any Drupal cache clear that may be required when deploying
updated code (though we try to minimize cache clears).

When a wiki page is updated by an author, the [changes route
controller](/src/Controller/OmnipediaWikiNodeChangesController.php) will
continue to show the previously rendered changes for a few minutes until the
cron process runs, in an attempt to always show something rather than the
fallback placeholder. It then renders and caches a separate version for every
unique user permissions hash, as different users may have different permissions,
e.g. authors can see contextual links to edit parts of the content.

So why did we engineer this complicated asynchronous rendering and caching? Most
changes only take a few seconds to generate, but a few pages could take upwards
of 30 seconds to generate, during which time you would be presented with a page
that wasn't loading. We saw this as very poor UX, and doing all of this ahead of
time while trying really hard to show something, even if it was out of date, was
our solution.
