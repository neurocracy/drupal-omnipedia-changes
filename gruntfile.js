module.exports = function(grunt) {

  'use strict';

  const childProcess = require('child_process');

  const aiModulesPath = childProcess.execSync(
    'drush ambientimpact:modules-path'
  ).toString().trim();

  const allComponentPaths = JSON.parse(childProcess.execSync(
    'drush ambientimpact:component-paths'
  ).toString().trim());

  const ownComponentPaths = JSON.parse(childProcess.execSync(
    'drush ambientimpact:component-paths --providers=omnipedia_changes'
  ).toString().trim());

  let pathTemplates = {
    allComponents: allComponentPaths,
    ownComponents: ownComponentPaths,
  };

  for (const propertyName in pathTemplates) {

    if (!pathTemplates.hasOwnProperty(propertyName)) {
      continue;
    }

    // Convert each path into a string, joined with a comma if multiple items
    // are found.
    pathTemplates[propertyName] = pathTemplates[propertyName].join(',');

    // Add braces if a comma is found, so that expansion of multiple paths can
    // occur. Note that we have to do this conditionally because a set of braces
    // with a single string will not have braces expanded but output with the
    // braces, resulting in a path that won't match.
    //
    // @see https://www.gnu.org/software/bash/manual/html_node/Brace-Expansion.html
    // @see https://gruntjs.com/configuring-tasks#templates
    if (pathTemplates[propertyName].indexOf(',') > -1) {
      pathTemplates[propertyName] = '{' + pathTemplates[propertyName] + '}';
    }

  }

  // Load our Grunt task configs from external files in the 'grunt' directory.
  require('load-grunt-config')(grunt, {
    init: true,
    data: {
      aiModulesPath:      aiModulesPath,
      allComponentPaths:  allComponentPaths,
      ownComponentPaths:  ownComponentPaths,
      pathTemplates:      pathTemplates,
    }
  });

  grunt.registerTask('css', [
    'sass',
    'postcss',
  ]);

  grunt.registerTask('all', [
    'css',
  ]);

  grunt.registerTask('default', ['all']);

};
