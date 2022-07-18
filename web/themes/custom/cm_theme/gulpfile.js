/**
 * @file
 * This is used to build the css and javascript.
 */

const gulp = require('gulp');
const imagemin = require('gulp-imagemin');
const uglify = require('gulp-uglify');
const rename = require('gulp-rename');
const pump = require('pump');
const sass = require('gulp-sass');
const livereload = require('gulp-livereload');
const cleanCSS = require('gulp-clean-css');
const autoprefixer = require('gulp-autoprefixer');

gulp.task('default', ['files', 'js', 'scss']);

gulp.task('files', () => {
  gulp
    .src([
      'images/**/*.jpg',
      'images/**/*.jpeg',
      'images/**/*.gif',
      'images/**/*.png',
    ])
    .pipe(imagemin())
    .pipe(gulp.dest('images'))
    .pipe(livereload())
    .on('error', error => {
      console.error(error);
    });
});

gulp.task('js', () => {
  pump(
    [
      gulp.src('js/*.js'),
      uglify(),
      rename({
        suffix: '.min',
      }),
      gulp.dest('js/min'),
      livereload(),
    ],
    err => {
      console.log('pipe finished', err);
    }
  );
});

gulp.task('scss', () => {
  gulp.src('scss/**/*.scss')
    .pipe(sass())
    .pipe(autoprefixer())
    .pipe(cleanCSS({compatibility: 'ie8'}))
    .pipe(gulp.dest('css/min/'))
    .pipe(livereload())
    .on('error', (error) => {
      console.error(error);
    });
});

gulp.task('watch', () => {
  livereload.listen();
  gulp.watch('js/*.js', ['js']);
  gulp.watch('scss/**/*.scss', ['scss']);
  // gulp.watch('images/**/*', ['files']);.
});

gulp.task('dev', ['js', 'scss']);
