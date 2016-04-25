const gulp = require('gulp');

const autoprefixer = require('gulp-autoprefixer');
const autoReload = require('gulp-auto-reload');
const concat = require('gulp-concat');
const cssmin = require('gulp-minify-css');
const exec = require('gulp-exec');
const htmlmin = require('gulp-htmlmin');
const fs = require('fs');
const imagemin = require('gulp-imagemin');
const merge = require('merge-stream');
//const pngquant = require('imagemin-pngquant');
const rename = require('gulp-rename');
const sass = require('gulp-sass');
const sourcemaps = require('gulp-sourcemaps');
const through = require('through2');
const uglify = require('gulp-uglify');
const args = require('yargs').argv;

const inlineImages = (function(root){
	// Embed SVGs (/* @inline */ url(...)
	return through.obj(function(file, encoding, callback){
		var contents	= file.contents.toString();

		contents	= contents.replace(/\/\*\s*@inline\s*\*\/\s*url\(\s*(['"])(.*?\.svg)\1\s*\)/, function(original, _, path){
			if(path === ''){
				return original;
			}
			if(path.substr(0,1) === '/'){
				// Absolute
				path	= root+path;
			} else {
				// Relative
				throw new Error('Cannot handle relative paths yet');
			}

			const content	= fs.readFileSync(path),
				dataURI	= 'data:image/svg+xml,'+encodeURIComponent(content);

			return 'url("'+dataURI+'")';
		});

		file.contents	= new Buffer(contents);
		callback(null, file);
	});
});

const noop	= require('through2').obj;

const dirSrc	= './src',
	  dirDest	= './dist',
	  dirData	= './data';

const environment	= args.environment || 'production',
	  isProduction	= environment === 'production',
	  isDev			= environment === 'development';

const years	= (function(){
	var files	= fs.readdirSync(dirData),
		years	= [];
	for(var i = 0; i < files.length; i++){
		var file	= files[i];
		if(file.match(/^\d+$/)){
			years.push(file);
		}
	}
	return years;
}());

function src(path){
	return dirSrc+'/'+(path.replace(/^\//, ''));
}
function dest(path){
	return gulp.dest(dirDest+'/'+(path.replace(/^\//, '')));
}

var watchSources	= {},
	defaultTasks	= [];
function registerTask(name, pattern, callback){
	defaultTasks.push(name);

	watchSources[pattern]	= [name];
	return gulp.task(name, function(){
		callback(pattern);
	});
}

registerTask('images', src('img/**/*.{jpg,png,gif,svg}'), function(source){
	return gulp
			.src(source)
			.pipe(isProduction
				? imagemin({
					optimizationLevel:	4,
					progressive:		true,
					svgoPlugins:		[{removeViewBox: false}],
					//use:				[pngquant()]
				})
				: noop()
			)
			.pipe(dest('img'));
});

registerTask('posters', 'data/*/posters/*.jpg', function(source){
	return gulp
			.src(source)
			.pipe(isProduction
				? imagemin({
					optimizationLevel:	4,
					progressive:		true,
				})
				: noop()
			)
			.pipe(rename(function(path){
				path.dirname	= path.dirname.replace(/^(\d+)\/posters/, 'posters/$1');
			}))
			.pipe(dest('img'));
});

registerTask('fonts', src('font/**/*.*'), function(source){
	return gulp
			.src(source)
			.pipe(dest('font'));
});

registerTask('scripts', src('js/**/*.js'), function(source){
	var pipe	= gulp
		.src(source)
		.pipe(sourcemaps.init());
	if(isProduction){
		pipe	= pipe
			//.pipe(concat('main.min.js'))
			.pipe(uglify())
			//.pipe(rename({
			//	suffix: '.min'
			//}))
			.pipe(dest('js'));
	}
	return pipe
			.pipe(sourcemaps.write('.', {addComment: false}))
			.pipe(dest('js'));
});

registerTask('styles', src('scss/**/*.scss'), function(source){
	return gulp
			.src(source)
			.pipe(sourcemaps.init())
			.pipe(sass())
			.pipe(autoprefixer({
				browsers:	['> 1%']
			}))
			.pipe(inlineImages(dirDest))
			.pipe(isProduction ? cssmin() : noop())
			.pipe(dest('css'))
			//.pipe(rename({
			//	suffix: '.min'
			//}))
			.pipe(sourcemaps.write('.'))
			.pipe(dest('css'));
});

var htmlInject	= noop;	// Noop
registerTask('html', src('index.php'), function(source){
	var streams	= merge();

	var max	= years.length;
	for(var i = 0; i < max; i++){
		var pipe	= gulp
			.src(source)
			.pipe(exec('php -f "<%= file.path %>" -- --year <%= options.customYear %> --environment "<%= options.customEnv %>"', {pipeStdout: true, customYear: years[i], customEnv: environment}))
			.pipe(rename(function(path){
				path.extname	= '.html';
				if(this != max-1){
					path.dirname	+= '/'+years[this];
				}
			}.bind(i)))
			.pipe(isProduction
					? htmlmin({
						removeComments:				true,
						collapseWhitespace:			true,
						conservativeCollapse:		true,
						removeTagWhitespace:		true,
						removeRedundantAttributes:	true,
						removeEmptyAttributes:		true,
						keepClosingSlash:			true,
						quoteCharacter:				'"',
					})
					: noop())
			.pipe(htmlInject())
			.pipe(dest(''));

		streams.add(pipe);
	}

	return streams;
});
registerTask('html:supporting', src('{404,500}.php'), function(source){
	// Check for environment flag
	return gulp
			.src(source)
			.pipe(exec('php -f "<%= file.path %>" -- --environment "<%= options.customEnv %>"', {pipeStdout: true, customEnv: environment}))
			.pipe(rename({
				extname: '.html'
			}))
			.pipe(
				isProduction
					? htmlmin({
						removeComments:				true,
						collapseWhitespace:			true,
						conservativeCollapse:		true,
						removeTagWhitespace:		true,
						removeRedundantAttributes:	true,
						removeEmptyAttributes:		true,
						keepClosingSlash:			true,
						quoteCharacter:				'"',
					})
					: noop()
			)
			.pipe(htmlInject())
			.pipe(dest(''));
});

registerTask('calendar', src('calendar.php'), function(source){
	// Check for environment flag
	return gulp
			.src(source)
			.pipe(exec('php -f "<%= file.path %>" -- --environment "<%= options.customEnv %>"', {pipeStdout: true, customEnv: environment}))
			.pipe(rename({
				extname: '.ics'
			}))
			.pipe(dest(''));
});

registerTask('resources', src('.htaccess'), function(source){
	// Copy to destination
	return gulp
			.src(source)
			.pipe(dest(''));
});

gulp.task('sync-data', function(){
	const year	= args.year;

	return gulp
			.src('./sync-data.php')
			.pipe(exec('php -f "<%= file.path %>" -- "<%= options.customYear %>"', {customYear: year}))
			.pipe(exec.reporter());
});

gulp.task('fetch-posters', function(){
	const year	= args.year;

	return gulp
			.src('./fetch-posters.php')
			.pipe(exec('php -f "<%= file.path %>" -- "<%= options.customYear %>"', {customYear: year}))
			.pipe(exec.reporter());
});

// Reload on data changes
watchSources[dirData+'/**/*.csv']	= ['html'];

gulp.task('html:autoreloader', function() {
	const reloader = autoReload();

	reloader.script()
		.pipe(dest(''));

	htmlInject	= reloader.inject;

	gulp.watch(dirDest+'/**/*', reloader.onChange);
});

gulp.task('watch', ['html:autoreloader', 'default'], function(){
	for(var source in watchSources){
		if(!watchSources.hasOwnProperty(source)){ continue; }
		gulp.watch(source, watchSources[source]);
	}
});

gulp.task('default', defaultTasks);
