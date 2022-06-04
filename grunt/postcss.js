module.exports = function(grunt, options) {

  'use strict';

  return {
    components: {
      options: {
        map: {
          inline: false
        },
        processors: [
          require('autoprefixer'),
        ]
      },
      files: [{
        src:
          '<%= pathTemplates.ownComponents %>/**/*.css',
        ext:  '.css',
        extDot: 'last',
        expand: true,
      }]
    }
  };

};
