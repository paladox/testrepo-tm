/*!
 * Grunt file
 */

/*jshint node:true */
module.exports = function ( grunt ) {
	grunt.loadNpmTasks( 'grunt-contrib-jshint' );
	grunt.loadNpmTasks( 'grunt-contrib-csslint' );
	grunt.loadNpmTasks( 'grunt-contrib-watch' );
	grunt.loadNpmTasks( 'grunt-banana-checker' );
	grunt.loadNpmTasks( 'grunt-jscs-checker' );

	grunt.initConfig( {
		pkg: grunt.file.readJSON( 'package.json' ),
		jshint: {
			options: {
				jshintrc: '.jshintrc'
			},
			all: [
				'resources/*.js',
				'MwEmbedModules/{EmbedPlayer,TimedText}/**/*.js'
			]
		},
		jscs: {
			src: [
				'<%= jshint.all %>'
			]
		},
		csslint: {
			options: {
				csslintrc: '.csslintrc'
			},
			all: [
				'resources/*.css',
				'MwEmbedModules/**/*.css'
			]
		},
		banana: {
			all: '{i18n,MwEmbedModules/EmbedPlayer/i18n,MwEmbedModules/TimedText/i18n}/'
		},
		watch: {
			files: [
				'.{csslintrc,jscsrc,jshintignore,jshintrc}',
				'<%= jshint.all %>',
				'<%= csslint.all %>'
			],
			tasks: 'test'
		}
	} );

	grunt.registerTask( 'test', [ 'jshint', 'jscs', 'csslint', 'banana' ] );
	grunt.registerTask( 'default', 'test' );
};
