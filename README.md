# Simplex #

A tool for web developers

### Table of Contents ###

* [Introduction](#introduction)
* [Requirements](#requirements)
* [Terminology](#terminology)
* [Pre-Installation - Upgrade Jobs](#pre-installation---upgrade-jobs)
* [Installation](#installation)
* [Upgrade](#upgrade)
* [Post-Installation Jobs](#post-installation-jobs)
* [Area set up](#area-set-up)
    * [Backend / ERP](#backend--erp)
* [Subject set up](#subject-set-up)
* [Debugging](#debugging)
* [Internazionalization](#internazionalization)
* [Emails](#Emails)
* [Icon Fonts](#Icon-Fonts)
* [Development to Production](#development-to-production)
* [Simplex Logic overview](#simplex-logic-overview)
    * [Logical Structure](#logical-structure)
    * [Application Flow](#application-flow)
* [Folders and Files Structure](#folders-and-files-structure)
* [Migration from v1 to v2](#migration-from-v1-to-v2)
* [Considerations](#considerations)
* [API Documentation](#api-documentation)
* [References](#references)

## Introduction ##

Simplex is a tool for developing PHP/HTML/CSS web applications. In short, it sets up a handy environment so you hopefully only have to code files specific to the project business logic: PHP (classes and some configuration file), HTML ([Twig](https://twig.symfony.com/doc/2.x/) templates) and CSS or [SCSS](https://sass-lang.com/).

The goal of Simplex is to provide:

* the structure for a PHP web application that is a compromise between:
    * simplicity
    * the latest standards and practices (as far as I know)
* a quick and easy way to get started developing code for the application

To do so Simplex relies on:
* [Composer](https://getComposer.org) packages (by means of [Packagist](https://packagist.org/) packages):
    * Simplex itself is a Composer package that:
        * takes care of installing the required libraries (for at least the minimum functionalities)
        * creates the basic starting structure for the application with some draft files almost ready to be used but that can be deleted, modified and integrated at need
    * other selected Composer packages are integrated to create the application core engine
* [NPM](https://npmjs.com) for Javascript and CSS packages
* [Bootstrap](https://getbootstrap.com/) as CSS framework
* [Fontello](http://fontello.com/) for icons

_NOTE ON THIS DOCUMENT_: I will try to be clear and write down all the details to understand and use Simplex, for future-me, any possible colleague and anyone else interested benefit

## Requirements ##

* server on Linux OS (mainly tested on Debian 10/11)
* webserver: Simplex has always used [Apache 2.4+](http://httpd.apache.org/), .htaccess files are used for basic routing; I guess it *could* be used also [Nginx](https://nginx.org/) also but it has ever been tested
* [PHP 8.2+](https://www.php.net/downloads.php) with the PHP [gettext](http://www.php.net/gettext) bcmath extensions enabled
* ssh access to web space: on a shared hosting it's hard to use Composer (and Npm and Sass), you have to develop locally and commit, but I really suggest to find a provider who can give you ssh access; once I tried the power & comfort of the ssh shell I rented my own virtual machine and never turned back to shared hosting again...
* [Composer](https://getComposer.org) aliased (globally or locally) as `composer` command
* a frontend package manager: Simplex suggests [npm](https://www.npmjs.com/) installed globally:
* [Sass](https://sass-lang.com/) (I currently use version 1.56.1 compiled with dart2js 2.18.4): to compile css with variables, mixings and many other useful additions and customize bootstrap

## Terminology ##

* __root__: the top folder of the Simplex installation, usually the top folder in the web accessible part of the site web space
* __application__: the customized installation of Simplex for the specific project/domain
* __private__: area of the application hidden from browsers
* __public__: area of the application open to browsers
* __share__: area of the application where third part libraries and files are store, mainly _private/share_ for [Packagist](https://packagist.org/) PHP libraries and _public/share/node_modules_ for [npm](https://www.npmjs.com/) frontend packages
* __environment__: in which the current request is handled, Simplex assumes the values of "development" and "production" but it's possible to define other variants on a per installation basis
* __area__: a part of the application matching a set of routes having properties, requirements and behaviours in common, i.e "Backend", "Frontend", "Cron", every route must set an 'area' parameter and it should be formatted as a [slug](https://en.wikipedia.org/wiki/Clean_URL#Slug)
* __subject__: into an ERP area, a subject is the name of the system formed by:
    * a controller
    * a set of routes handled by the controller
    * the set of actions corresponding to these routes
    * the controller methods corresponding to these actions
    * the model each of this actions operate on
* __action__: the specific logic associated to a route, i.e. 'list' or 'save-form', every route must set an 'action' parameter and it should be formatted as a [slug](https://en.wikipedia.org/wiki/Clean_URL#Slug)

Conventions:

* in the following explanation files and paths are written in _italic_
* for each file is always given the path from the root, without leading slash

## Pre-Installation - Upgrade Jobs ##

Before installing from scratch or upgrading from 2.x:

* download [ini config file draft](https://github.com/vukbgit/simplex/blob/development/installation/config.draft.ini)
* save it into web space __outside__ root folder as _config.ini_
* edit and set at least mandatory values following comments
* set permanently the PATH_TO_INI_CONFIG environment variable to the absolute path to the file, adding to system user .bashrc file (usually something like _home/user-name/.bashrc_ ) the file 
  
        export PATH_TO_INI_CONFIG=absolute/path/to/config.ini

* if logged into same console where installation/upgrade will be executed, log-out to make $PATH_TO_INI_CONFIG available to scripts
        
## Installation ##

Create a composer.json in the root folder:

    {
      "type": "project",
      "name": "vuk/simplex",
      "description": "Simplex app",
      "license": "MIT",
      "require": {
        "vukbgit/simplex": "^3.0"
      },
      "config": {
        "vendor-dir": "private/share/packagist",
        "bin-dir": "./"
      },
      "autoload": {
        "psr-4": {
          "Simplex\\Local\\": "private/local/simplex"
        }
      },
      "scripts": {
        "post-create-project-cmd": [
          "SlowProg\\CopyFile\\ScriptHandler::copy"
        ],
        "pre-update-cmd": [
          "Simplex\\Refactor::preRefactoring"
        ],
        "post-update-cmd": [
          "Simplex\\Refactor::postRefactoring"
        ]
      },
      "extra": {
        "copy-file": {
          "private/share/packagist/vukbgit/simplex/installation/": "."
        }
      }
    }

Notes: for version constraint (^3.0) check last tag on github repository or non-develop version number on packagist.

Create the Composer project running on command line in the root folder:

        composer create-project

Simplex will:

* install itself and the other required Composer libraries
* copy in the root directory some files 
* make symlinks in the root directory to some shell scripts ([Composer vendor binaries](https://getcomposer.org/doc/articles/vendor-binaries.md))
* build the folders structure for the local application with some ready draft files

For details see [Folders and Files Structure](#Folders-and-Files-Structure) below

## Upgrade ##

### From 2.x version ###

* follow [Pre-Installation - Upgrade Jobs](#Pre-Installation---Upgrade-Jobs)
* add into composer.json to the "scripts" array the post update refactoring script:

      "post-update-cmd": [
        "Simplex\\Refactor::postRefactoring"
      ]

* run the `composer upgrade` command
* look into output for any message that requires an action
* eventually examine the refactoring logs saved into _private/local/log_
* add into composer.json to the "scripts" array the pre update refactoring script:

      "pre-update-cmd": [
        "Simplex\\Refactor::preRefactoring"
      ]

* if necessary add to .gitignore `private/local/simplex/log/`

### From a 3.x version less than 3.13 ###

* take note of current version, i.e. `./composer.sh show vukbgit/simplex`
* update composer packages: `./composer.sh update`
* call refactor.php for PRE stage with old version, i.e.: `php8.2 private/share/packagist/vukbgit/simplex/bin/refactor.php pre 3.4.0`
* call refactor.php for POST stage, i.e.: `php8.2 private/share/packagist/vukbgit/simplex/bin/refactor.php post`
* look into output and logs for any message that requires an action
* add into composer.json to the "scripts" array the pre and post update refactoring script:

      "pre-update-cmd": [
        "Simplex\\Refactor::preRefactoring"
      ],
      "post-update-cmd": [
        "Simplex\\Refactor::postRefactoring"
      ]
    
* if necessary add to .gitignore `private/local/simplex/log/`

## Post-Installation Jobs ##

* _config.ini_: 
  * should be already in place (see [Pre-Installation - Upgrade Jobs](#Pre-Installation---Upgrade-Jobs)) with minimal configuration (otherwise nothing works...)
  * use this file to store any local information that must be as secret as possible like database credentials, passwords, secret API keys, etc.
  * simplex bash scripts all include _private/share/packagist/vukbgit/simplex/bin/bootstrap.sh_ which needs to include _config.ini_ so path (absolute or relative to the top level called script) must be provide as:
    * the PATH_TO_INI_CONFIG environment variable, which you can export as `export PATH_TO_INI_CONFIG=path/to/config.ini`:
      * temporarily into ssh session
      * permanently into _home/user-name/.bashrc_ (or equivalent, which you shold already done before installation)
    * or passing to the called script the -i option followed by the absolute/relative path to the ini config file, i.e. `simplex-script.sh -i path/to/config.ini`
* _/.htaccess_:
  * set default redirections, i.e _/backend_ to _/backend/sign-in-form_; in any case either you're going to define a route for the plain domain request or you redirect domain request to a default route (i.e. `RewriteRule ^/?$ /my-default-route [R,L]`); for routes definitiosn see below
* __index.php__: set `$pathToIniConfig` to the absolute o relative path to ini config file
* install __public__ packages:
    * the _public\share\package.json_ file contains some NPM packages for:
        * the whole application:
            * [bootstrap 5](https://getbootstrap.com)
            * [jquery latest](http://jquery.com/)
            * [js-cookie](https://github.com/js-cookie/js-cookie): javascript library to handle cookies
            * [mjml](https://www.npmjs.com/package/mjml): for responsive emails creation
            * [fontello-cli](https://www.npmjs.com/package/fontello-cli): to download Fontello icons
        * backends or enterprise web applications (ERP) mostly (even if they can be useful also in frontend development):
            * [bootstrap-fileinput](https://plugins.krajee.com/file-input): for asyncronous file upload. NOTE: as for 2019-11-12 version is frozen to 5.0.4 due to a bug in auto upload
            * [flatpickr](https://github.com/flatpickr/flatpickr): for date time picker
            * [jodit](https://github.com/xdan/jodit): for richtext input
            * [select2](https://select2.org): for richselect input with autocomplete, with [select2-theme-bootstrap5](https://github.com/angel-vladov/select2-theme-bootstrap5) for skinning
            * [hamburgers](https://github.com/jonsuh/hamburgers): CSS hamburger animated icons
            * [fullcalendar](https://github.com/fullcalendar/fullcalendar): full-sized event calendar
        * frontend
            * [jquery.safemail](https://github.com/leftclickcomau/jquery.safemail): for obfuscating email
            * [flag-icon-css](https://github.com/lipis/flag-icon-css): SVG country flags
    * edit the file if needed to include/exclude packages
    * install packages: Simplex is prearranged to use npm by means of the script _private/share/packagist/vukbgit/simplex/bin/npm.sh_ symlinked into root folder:
        * `./npm.sh install`
    * packages are installed under _\public\share\node_modules_
* local __configuration__: file _private\local\simplex\config\constants.php_ defines some PHP constants, most of them regard paths and it should not be necessary to modify them unless you plan to change the filesystem structure Simplex relies on; informations to be set for the local application (tech email, brand name...) are grouped into the _LOCAL_ block at the top of the script
* __languages__: 
    * file _private\local\simplex\config\languages.json_ defines used languages, contains by default definitions for English and Italian, add as many as required; language selection works this way:
        * if current route contains a 'lang' parameter (tipically a part of the route itself for multilanguage sites) that language is used
        * otherwise the first language defined into _private\local\simplex\config\languages.json_ is used, so if you only need one language you can put its definition in first position
* set up application _template_: a Twig template to be used for the whole application is provided in _private/local/templates/application.twig_:
    * it includes a Twig macro to display favicon generated by https://www.favicon-generator.org, if you want to use it go to website, generate icons images, upload them (i.e. to _public/local/corporate/favicon_) and set path as argument to the favicon() macro
* set up _routes_:
    * as seen above in the _.htaccess_  section, when browser points to your-domain.ltd there are two options:
        * an empty route is defined
        * .htacces redirects requests for plain domain to a default route
    * routes are set up into route.php files, which are stored into _config_ folders
    * as explained into the _terminology_ section above, Simplex logic is to divide application into logical areas, corresponding to folders; Simplex is shipped with a _private/local/simplex/Backend_ folder as a draft for building a backend for the application, it contains a _config/routes.php_ where 3 routes are defined for the login operation (display the login form, perform login authentication and logout)
    * Backend folder can be renamed to something else to suit application logic but some adjustments must be made (area variable value in _routes.php_ and _templates/backend.twig_ file name)
    * there is also a _private/local/simplex/SubjectGroup_ folder which is a draft for the development of a subject
* compile _SASS_ files to CSS:
    * Simplex encourages compilation of SASS source files (.scss) to CSS files (.css).
    * _private/local/simplex/config/sass.config_ contains a map of SASS to CSS files, each couple marked by an id (see file for explanations) 
    * _sass.sh_ is the soft link to a shell script that is used to compile css files, into root folder:

            ./sass.sh id-of-sass-file-to-be-compiled
    
    * Simplex need the compilation of at least these files:
        * application (_private/local/simplex/sass/application.scss_): style for the whole application (included into _private/local/simplex/templates/application.twig_):
            * it includes:
                * _share/vukbgit/simplex/src/sass/simplex.scss_ the Simplex style, cannot (should not...) be customized
                * _private/local/simplex/sass/variable.scss_ which contains sass variables for the whole application, can/should be customized and extended
            * compile with: `./sass.sh app`
        * bootstrap: different application areas might need different Bootstrap components, so there is not a predefined SCSS but a couple of drafts:
            * minimal (_private/local/simplex/sass/bootstrap.scss): `./sass.sh bs`
            * backend (_private/local/simplex/Backend/sass/bootstrap.scss): all the components needed by the Simplex backend templates: `./sass.sh bsb`

## Area set up ##

* every route must be associated to an area (see "Terminology")
* an area needs at least some configuration files and probably a template and a css file
* Simplex provides two starting areas, Backend and Frontend, to be tweaked and used as-is or as drafts for other areas

### Backend / ERP ###

Simplex is shipped with an ERP namespace draft and uses it to build backends and ERP applications. Here we assume the area name is "Backend" but it can be changed to anything else, you just need to rename Backend folder accordingly and substitute the "backend" word (minding letters case) accordingly. Besides the common operations discussed above, here are the steps to configure the provided Backend area :
* _.htaccess_: set redirection of _/backend_ route to _/backend/sign-in-form_
* check and set up _variables_ into _private/local/simplex/Backend/config/variables.php_, at least _$successfulSignInRoute_ (defaults to backend/dashboard), the other default values should be ok out of the box
* set up _authentication_ methods into _private/local/simplex/Backend/config/routes.php_ :
    * _htpasswd_: (enabled by default)
        * points to a htpasswd file that must be set manually, cd into desired folder (defaults to _private/local/simplex/Backend/config_):

                htpasswd -c .htpasswd your-username
            
        * every user defined in this file must be given a role into _private/local/simplex/Backend/config/users-roles.php_
    
    * _database_: (commented by default) uses a table or view, specifies fields names and conditions (like a boolean field which stores whether user is active)
* set up _logoPath_ into _private/local/simplex/Backend/templates/sign-in-form.twig_ to point to a logo image to be displayted into login form
* set up _logoPath_ into _private/local/simplex/Backend/templates/backend.twig_ to point to a logo image to be displayted into masthead, navbar height defaults to 48px, it can be twiked into _private/local/simplex/sass/variables.scss_
* customize Area style (_private/local/simplex/Backend/sass/backend.scss_) that just include ERP style and related variables (_private/local/simplex/Backend/sass/variables.scss_)
* compile the SASS files:
    * sign-in: `./sass.sh si`
    * backedn: `./sass.sh be`
* set up the _subject_ called by the $successfulSignInRoute route (see "Subject set-up below"):
* to integrate jodit uploader:
  * into root folder run `./composer.sh  create-project --no-dev jodit/connector public/share/jodit`
  * add to any rich text input the (built by a displayRichTextEditor macro) configuration to display upload related Jodit buttons (i.e. image) and uploader path:

                ...
                options: {
                  buttons: 'bold, italic, underline, strikethrough | ul, ol | cut, copy, paste | indent, outdent, left, right, center, justify | link | image | undo, redo | source,fullsize, preview | about',
                  uploader: {
                    url: '/public/share/jodit/index.php?action=fileUpload',
                  }
                }
                ...

  * into _public/share/jodit/Application.php_ remove the exception from _checkAuthentication_ method and add eventual authentication logic
  * copy _public/share/jodit/default.config.php_ as _public/share/jodit/config.php_
  * create a folder for uploaded images into public path, i.e. _public/local/simplex/richTextUploads_
  * if using a cvs system
    * include jodit in commit, edit _public/share/jodit/.gitignore.php_:

                composer.lock
                .idea/

                tests/_data/
                tests/_output/
                tests/_support/
                tests/acceptance/
                tests/files/


    * exclude folder from commits, i.e. for Git:
      * create a _public/local/simplex/richTextUploads/.gitignore_ file
      * paste into file:

                # Ignore everything in this directory
                *
                !.gitignore
      
  * edit _public/share/jodit/config.php_ and:
    * set up sources folders, i.e.

                $basePath = '/public/local/simplex/';
                $richTextUploadFolder = $basePath . 'Foo/richTextUploads';

                $config = [
                    'sources' => [
                      'default' => [
                        'root' => $_SERVER['DOCUMENT_ROOT'] . $richTextUploadFolder,
                        'baseurl' => ((isset($_SERVER['HTTPS']) and $_SERVER['HTTPS']) ? 'https://' : 'http://') . $_SERVER['HTTP_HOST'] . $richTextUploadFolder . '/',
                        'extensions' => [
                          'jpg',
                          'png',
                        ]
                      ],
                    ],
                ]

      * edit other options such as access control

This is the Backend folder structure:
* _private/local/simplex_
    * _Backend_
        * _config_
            * _constants.php_:
                * AREA_NAVIGATION_PATH: path to file with navigation menu definition, dfefaults to _private/local/simplex/Backend/config/navigation.php_
            * _di-container.php_: DI container definitions, shipped with just the authentication controller definition, it should not be necessary to modifiy or add definitions here, unless you have a class to be used across the whole area
            * _navigation.php_: navigation menus definition, return an array with one ore more menus definitions to be shared among area's pages, see file for format details
            * _premissions-roles_: map between permissions keys and area users roles (as defined into the authentication system), only necessary if you want to restrict access to some actions to some roles
            * _route.php_: definitions for area routes not related to some specific subject (i.e. sign in form)m shipped with default sign in form, sign in and sign out routes; contains some hard coded paths to authentication realted files that should not be necessary to change
            * _users-roles_: map between users names and roles, it is decoupled from authentication system (and user retrival data) to be as flexible as possible
            * _variables_: area level variables (such as slugged area name and default authentication object to be used in area routes), included into subjects di-container.php and routes.php specific file
        * _sass_:
            * _bootstrap.scss_: Bootstrap 4 components to be included in Bootstrap area style sheet, preset for minimal standard functionalities (menu, form, tables...), customize and recompile at need
        * _templates_: sign-in form and area shared template, used mainly to store translations and logo path, to be customized
    * _SubjectGroup_: subjects namespace can be organized into levels, useful when there are more the ten subjects at play
        * _Subject_: sdubjec level files, every type of file regarding the subject should be stored into its folder
            * _config_:
                * _crudl.php_: definitions for CRUDL functionalities, such as input filters to be used to grab fields values from save form or whether to use field to filter displayed table records
                * _di-container.php_: DI container definitions, shipped with just the controller and model definitions, it should be enough for standard subjects
                * _model-php_: model configuration (table. view. pruimary key)
                * _navigation.php_: definition for navigation associated to subject actions, shipped with default CRUDL actions (list, insert, update and delete), it should be enough for standard subjects
                * _routes.php_: definition for routes associated to subject actions, shipped with one definition that covers default CRUDL actions (list, insert, update and delete), and any action identified by one slugged action key and an optional primary key value, it should be enough for standard subjects
                * _variables_: subject level variables (such as subject namespace and slugged subject name )
            * _templates_: subject related templates, shape up subject UI and provide translations
                * _crudl-form.twig_: HTML for subject insert, update and delete forms by using macros defined into _private/share/packagist/vukbgit/simplex/src/templates/form/macros.twig_ or by writing HTML code directly
                * _list.twig_: template for subject records list, defaults to table, it's necessary to set up table headers and cells
                * _subject.twig_: contains subject labels
            * _Controller.php_: base subject controller class, extends _Simplex\Erp\ControllerAbstract_, correct namespace must be set but already inherits all of the methods needed by CRUDL operations, to be extended for additional actions (with protected methods named after the PSR1 form of the slugged action name)
            * _Model.php_: base subject model class, extends _Simplex\Model\ModelAbstract_, correct namespace must be set but already inherits all of the methods needed by CRUDL operations, to be extended for additional actions (with protected methods named after the PSR1 form of the slugged action name)
    * _bin_: shell scripts, it can be useful to soft link them into web root
        * _composer.sh_: to use composer with a PHP versione different from the system default one
    * _config_: application configuration files
        * _constants.php_: application brand label and tech email (to be showed into exceltions) must be set, other values are meinly paths that should not be necessary to change
        * _db.php_: database account settings (takes data from _config.ini_)
        * _languages.json_: languages available to application, first one is used as default, shipped with Italian (default for most of my applications) and English, customize at need
        * _sass.config_: used by the web root _sass.sh_ bash script to speed up SASS files compilation, shipped with some ready to use common paths, see file for details on format
    * _sql_: this folder might contain useful text files with SQL commands and snippets
        * _views.sql_: databases views definition, I usually keep them here since I find it more handy for editing
    * _sass_: application level SASS files
        * _application.scss_: rules to be applied to the whole applications
        * _bootstrap-variables.scss_: to override Bootstrap variables, included into bootstrap.scss BEFORE the Bootstrap own variables file
        * _bootstrap.css_: this file can be used to compile an application level bootstrap file if there is no need to have area level ones
        * _functions.scss_: SASS functions
        * _variables.scss_: application specific SASS variables i.e. colors or some specific ERP settings (navbars dimensions)
    * _templates_:
        * _application.twig_: ready to use top level application template
* _public_
    * _local_: folder for application files accessible by browsers, will be filled at least by compiled CSS files and probabily by much more application assets
    * _share_
        * _package.json_: default NPM packages
        * _npm.sh_: symlinked into web root to handle npm commands
    * _.htaccess_: Apache configuration file, grants access to real files (CSS, Javascript, imagess, etc) and redirects every other request to index.php

## Subject set up ##

The following steps show how to set up an ERP subject, that is a subject which implies a database model and the related CRUD UI, as the major part of a backend's subjects should be; it is also possible to use into a backend other kind of subjects (a dasboard for example), in this case see the frontend explanation below

* set up subject _database architecture_: besides a main table, in order to deal with internazionalization and file upload, Simplex relies over two accessory table, here is the complete overview of the possible subject architecture:
    * the main _table_ holds language-indipendent data, at least the primary key; it should be named after the model key, usually snake case even if it can be any name as long as it is set into _config/crudl.php_
    * _internazionalization_: Simplex aims to be localization ready, so there should be a table for any language-dependent information, named _main-table-name_locales_ (with the mandatory _locales suffix), with the following structure (naming convention is to be sctrictly followed for Simplex to handle correctly data saving):
        * a primary key (integer autoincrement) named _this-table-name_id_ (with the mandatory _id suffix)
        * a _language_code_ (char 2) field, which hold the languages codes used as key into _config/languages.json_ (the ISO-639-1 codes)
        * a _foreign key_ field targeted to the main table primary key with a CASCADE delete setting
        * any custom field (text or varchar) for localized pieces of information as needed by subject
    * in case subject needs _files upload_, define a table named _main-table-name_uploads_ (with the mandatory _uploads suffix)
        * this is the table structure:
            * a primary key (integer autoincrement) named _this-table-name_id_
            * a _foreign key_ field targeted to the main table primary key with a CASCADE delete setting
            * a field named _upload_key_ (varchar 64 should be enough) to store the record type of upload (the "field" used as key into the "uploads" array defined into _/config/model.php_)
            * a field named _file_name_ (varchar 256) to store the record type of upload (the "field" used as key into the "uploads" array defined into _/config/model.php_)
        * uploads information need to be manually added to the view (see below)
    * the _view_:
        * a basic view should be created and set up into _config/model.php_
        * in case of _uploads_ the view should be joined over the uplods table:
            * for each upload field defined into _config/model.php_ add a left join to the uploads table on the primary key and the _upload_key_ field (with the value of the field key)
            * extract _file_name_ field values with the name of the upload key; if field allows multiple uploads, group values, order them by primary key (so that order can by set by dragging files in the GUI) and join tem with a pipe: `,GROUP_CONCAT(DISTINCT join-table.file_name ORDER BY join-table.primary-key SEPARATOR '|') AS upload-key`
            * group by primary key in case of upload fields that allow multiple uploads in the UI
        * in case of a localized subject a localized view named _main-table-name_locales_ is mandatory: it must join main table over the locales one and expose primary keys fields, _language_code_ field (besides localized informations of course)
        * example:
            * table "foo" defined as 

                    CREATE TABLE `foo` (
                        `foo_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                        `non_localized_string` varchar(48) NOT NULL,
                        `date` date,
                        PRIMARY KEY (`foo_id`)
                    )
            
            * uploads definiton into subject _config/model.php_:

                    ...
                    'uploads' => [
                        'pdf_file' => [
                            'raw' => (object) []
                        ],
                        'single_image' => [
                            'thumb' => (object) [
                                'handler' => ['\Simplex\Local\SUBJECT\NAMESPACE\Controller','resizeImage'],
                                'parameters' => [100,100]
                            ],
                            'full' => (object) [
                                'handler' => ['\Simplex\Local\SUBJECT\NAMESPACE\Controller','resizeImage'],
                                'parameters' => [800,600]
                            ]
                        ],
                        'multiple_image' => [
                            'thumb' => (object) [
                                'handler' => ['\Simplex\Local\SUBJECT\NAMESPACE\Controller','resizeImage'],
                                'parameters' => [100,100]
                            ],
                            'full' => (object) [
                                'handler' => ['\Simplex\Local\SUBJECT\NAMESPACE\Controller','resizeImage'],
                                'parameters' => [800,600]
                            ]
                        ]
                    ],
                    ...
                    
            * the uploads table:

            
                    CREATE TABLE `foo_uploads` (
                    `foo_uploads_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `foo_id` int(10) unsigned NOT NULL,
                    `upload_key` varchar(64) NOT NULL,
                    `file_name` varchar(256) NOT NULL,
                    PRIMARY KEY (`foo_uploads_id`),
                    KEY `foo_id` (`foo_id`),
                    CONSTRAINT `foo_uploads_ibfk_1` FOREIGN KEY (`foo_id`) REFERENCES `foo` (`foo_id`) ON DELETE CASCADE
                    ) 
                
            * basic view:

                    CREATE VIEW v_foo AS SELECT
                    f.*
                    ,fusi.file_name AS single_image
                    ,GROUP_CONCAT(DISTINCT fumi.file_name ORDER BY fumi.foo_uploads_id SEPARATOR '|') AS multiple_image
                    FROM foo AS f
                    LEFT JOIN foo_uploads AS fusi
                    ON f.foo_id = fusi.foo_id
                    AND fusi.upload_key = 'single_image'
                    LEFT JOIN foo_uploads AS fumi
                    ON f.foo_id = fumi.foo_id
                    AND fumi.upload_key = 'multiple_image'
                    GROUP BY f.foo_id
                    
            * the internationalization table:

                    CREATE TABLE `foo_locales` (
                    `foo_locales_id` int(10) unsigned NOT NULL AUTO_INCREMENT,
                    `language_code` char(2) NOT NULL,
                    `foo_id` int(10) unsigned NOT NULL,
                    `title` varchar(256) NOT NULL,
                    `content` text NOT NULL,
                    PRIMARY KEY (`foo_locales_id`),
                    KEY `foo_id` (`foo_id`),
                    CONSTRAINT `foo_locales_ibfk_1` FOREIGN KEY (`foo_id`) REFERENCES `foo` (`foo_id`) ON DELETE CASCADE
                    )
                    
            * the internationalization view:

                    CREATE VIEW v_foo_locales AS SELECT
                    f.*
                    ,fl.language_code
                    ,fl.title
                    ,fl.content
                    FROM v_foo AS f
                    LEFT JOIN foo_locales AS fl
                    ON f.foo_id = fl.foo_id

    * Simplex is shipped with a convenient _private/local/simplex/docs/views.sql_ text file where views definition can be written; often database manager SQL editor are not handy, storing views definition into a plain SQL/text file, editing through an editor with synthax highlighting and copy and paste into the db application can be a solution and provides also a backup
* each subject files are contained into a folder named after the subject
* ponder the position of the subject into application architecture:
    * it can be used application wide, so its folder should reside into _private/local/simplex_
    * it can be used only inside ont specific area, so it should reside into _private/local/simplex/area-name_
    * it can share some business logic with other subjects, so it should reside into _private/local/simplex/[area-name]/subjects-group-name_; in case application uses more than a few subjects I usually try to group them
* use _private/local/simplex/SubjectGroup/Subject_ folders as a draft, copy them and customize files; or even better use an already configured subject folder and edit just the files formatted below in ___bold italic___. Working into subject folder:
    * ___config/variables.php___: set up subject namespace and slug form, namespace must reflect subject folder position (i.e. _Simplex\Local\Backend\Subject-Group-Name\Subject-Name_)
    * ___config/model.php___: set up subject model definition
    * rename _config/routes.draft.php_ to _config/routes.php_, default configure dynamic route should cover at least the basic CRUD operations (list, inser form, insert operation, update form, update operation, delete form, delete operation)
    * rename _config/di-container.draft.php_ to _config/di-container.php_
    * edit ___Controller.php___ and ___Model.php___ and correct namespace to the same value used into _config/routes.variables.php_
    * edit ___config/crudl.php___ and set up:
        * table localization (localized property, boolean), whether model table uses an accessory table for localization (see "set up _database architecture_" above)
        * table fields filters to be used to retrieve fields values passed by save form
    * _config/navigation.php_ contains rules to display the UI navigation for basic CRUD actions, it can be customized to remove some of them or add more actions, for permissions logic see _subject permission_ below
* set up subject _template labels_ in ___templates/labels.twig___, at least subject label is required but fields labels are used int list and save form templates
* edit ___templates/list.twig___ to set up fields displayed into records table, there is a tableheader block for headers and a records loop for table cells with fields values
* edit ___templates/crudl-form.twig___ to set up fields displayed into insert/update and delete forms; any valid html can be inserted into modelInputs block but a bunch of useful Twig macros are defined into _/private/share/packagist/vukbgit/simplex/src/templates/form/macros.twig_ which is included by default; use macros whose name end by 'group' di build a Bootsrap field form complete with label
* set up _subject permission_ for roles into ___private/local/simplex/Backend/config/permissions-roles.php___:; by default into _private/local/simplex/Backend/config/navigation.php_ permission _manage-SUBJECT-KEY_ is required for the user to use global (list and insert) and record actions (update and delete), so permission _manage-SUBJECT-KEY_ must be added and user's role must be included into permission's allowed roles array
* include subject into area navigation into ___private/local/simplex/Backend/config/navigation.php___
* set up subject _navigation label_ in ___private/local/simplex/Backend/templates/backend.twig___, into the _areaNavigationLabels_ hash
* perform sign-out/sign-in to reload permissions
        
## Debugging ##

* PHP layer: Simplex uses [Whoops](https://filp.github.io/whoops/) for printing exceptions in development environment and [Kint](https://kint-php.github.io/kint/) for dumping variables; in particular Kint is available as two functions:
    * 'x($var)': dumps a variable to HTML aoutput
    * 'xx($var)': dumps a variable and exits the scripts immediately
* Twig templates: the dump function is available as a wrapper for Kint dump function, i.e. '{{ dump(my-variable-of-any-type) }}'

## Internazionalization ##

* __PHP/Twig templates__:
    * Simplex uses [gettext](https://www.gnu.org/software/gettext/) and is shipped with a set of English and Italian translations for the messages contained into the provided UI
    * translations can be inserted into:
        * _PHP_ files using the gettext() function or the _() alias (see [PHP gettext documentation](https://www.php.net/gettext))
        * _Twig templates_ using the trans block and filter from the (included by default) i18n extension (see [documentation](https://twig-extensions.readthedocs.io/en/latest/i18n.html))
    * _private/share/packagist/vukbgit/simplex/bin/translations.php_ script can be used to extract translations
    * folder where translations files are stored is specified by the _TRANSLATIONS_DIR_ constant and defaults to _private/local/simplex/locales_
    * this is the translations workflow:
        * after installation, into _TRANSLATIONS_DIR_ English and Italian translations for Simplex core messages are stored
        * translations extraction from relies on Twig templates cache; if your local templates use any user defined Twig filter or function templates cache building will be broken, so you need to:
            * put the Twig filters/functions definitions into a public method of some of your local classes
            * create one or more files under _private/local/simplex_ named _templates-helpers.php_: if this file exists it sill be automatically included by the translation script (there is a draft file _private/local/simplex/bin/templates-helpers.draft.php_)
            * into the _templates-helpers.php_ file call all of the template helpers builder methods
        * when local translations are added run script to update local messages, .po and .mo files are saved into _TRANSLATIONS_DIR_ for every language defined into _private/local/simplex/config/languages.json_:

                php private/share/packagist/vukbgit/simplex/bin/translations.php update local
            
        * download .po files, translate with [Poedit](https://poedit.net/) or other similar software
        * upload resulting .po and .mo files back to _TRANSLATIONS_DIR_
    * in case site uses a PHP version different from system one if must be specified the complete path to PHP binary, i.e.

            /opt/php-7.3.5/bin/php private/share/packagist/vukbgit/simplex/bin/translations.php update local
            
    * at installation time it is also created in the root folder the script _translations.sh_ that can be used to shortcut translation process, call it from root folder this way:

                ./translations.sh update local
* __database__: Simplex encourages a localization ready database architecture, even when site uses just one language (see [Subject set up](#Subject-set-up) > _database architecture_)

## Emails ##

Simplex uses [mjml](https://www.npmjs.com/package/mjml) integrated into Twig templates by means of [mjml-php](https://github.com/qferr/mjml-php) to build responsive emails. A starting templates is provided into _private/local/simplex/templates/emails/base.twig_

## Icon Fonts ##

Simplex uses [Fontello](http://fontello.com/) for icons and it breaks icons into logical groups, each with its Fontello folder, so far:
* _public/share/simplex/form/Fontello_ for form related icons (100 reserved unicode values, from 0100 to 0164)
* _public/share/simplex/Erp/Fontello_ for ERP (Backend) related icons (100 reserved unicode values, from 0165 to 01C9)
* _public/share/brands/Fontello_ for brands related icons, i.e. social media or file types (20 reserved unicode values, from 01CA to 01DE)

Unicode codes are assigned so that, if icons are used into css (setting CSS properties _content_ to unicode code and _font-family_ to "fontello") icons do not overlap, if other application specific icons are added they should take unicode codes from 02BC (included) onward.
Note: when using unicode codes into CSS remember to check text-transform (lowercase or uppercase) because each icon corresponds to a unicode charachter with a specific case and, if element inherits case from context, it could display the wrong gliph.

## Development to Production ##

If you keep separate development and production environment and manage pubblication through a git repository you can use some bash scripts soft linked into web root at installation time:
* development environment:
    * _git-setup-dev.sh_: interactive script that asks for repository URL, git user email and git user name, sets up the repository, makes first significative commit and pushes it to repository
    * _git-push-all.sh_: push all changes made since last commit, it adds all content of root folder and subfolders, so any folder/file to be excluded from commits must be added to _.gitignore_; if you need to push only some changes you must add, commit and push manually
* production environment:
    * copy _composer.json_ file from development environment
    * run `composer create-project`
    * run _git-setup-prod.sh_, interactive script that asks for repository URL and sets up repository for pulling
    * run _update-all.sh_ which:
        * cleans DI container and templates cache, beware of tmp folder path, (see _[Post-Installation Jobs](#Post-Installation-Jobs) > bash scripts settings_
        * updates Composer packages
        * updates NPM packages through npm

## Simplex Logic overview ##

### Logical Structure ###

* __Simplex\ControllerAbstract__: basic controller for a route that does not display a page
    * has DI container and response object as injected dipendencies 
    * route MUST pass 'action' (in slug form) and 'area' parameters
    * stores request and its parameters parameters
    * sets language
    * executes the method named after the PSR1 translation of the action parameter slug
* __Simplex\ControllerWithTemplateAbstract__: controller for a route that displays a page
    * extends Simplex\ControllerAbstract so inherits its properties and functionalities plus the following
    * has template engine and cookies manager as injected dipendencies 
    * builds some template helpers
    * passes to template some constants and variables as parameters
* __Simplex\Erp\ControllerAbstract__: controller for a route into an ERP area of the application
    * extends Simplex\ControllerWithTemplateAbstract so inherits its properties and functionalities plus the following
    * identifies the current __subject__ (see "Terminology" above):
        * all the files relative to a subject MUST be in the same folder, inside the _Simplex\Local _ namespace
        * the subject name is the name of the folder files are into (which is the name of the last part of the subject's files namespace) turned from PSR1 format to slug (with every upper case letter prepended by hypen and turned to lower case, i.e. 'NewsCategories' > 'news-categories')
        * stores the subject under _$this->subject_
    * instances and injects the mandatory __model__:
        * there MUST be a class defined into subject namespace that:
            * is named 'Model'
            * must extend Simplex\Model\ModelAbstract
            * must have git a configuration file under _subject-namespace\config\model.php_
        * stores the model under _$this->model_
    * loads CRUDL config which is mandatory for ERP and contains informations for the CRUDL interface to be exposed (such as the input filters to be used with each model field) and must be saved into a _subject-namespace\config\crudl.php_
    * gets users options for the subject from cookies (options are set into cookie from the UI) and stores them into _$this->userOptions_
    * loads navigations:
        * load area navigations (side bar menu) from the file _area-namespace\config\navigation.php_
        * load subject navigations (globa actions tabs, table record action links) from the file _subject-namespace\config\navigation.php_
        * each route contained into navigations voices is tested against current URL to check whether it's the currently selected one
    * builds some ERP specific template helpers
    * passes to template some ERP specific constants and variables as parameters

### Application Flow ###

* _.htaccess_ 
    * sets a PHP environment variable base on the domain to decide the current environment
    * intercepts every request and redirects to _index.php_
* _index.php_:
    * requires Composer autoload
    * searches for files named _constants.php_ under folder _private/local/simplex_, during installation _private/local/simplex/config/constants.php_ is created (see file for details)
    * set up the __Error Handler__ based on the environment
    * instances a __[Dipendency Injector Container](https://github.com/php-fig/container)__ loading definitions from _private/share/packagist/vukbgit/simplex/config/di-container.php_ (see file for details)
    * the __DI Container__ instances the __Dispatcher__ (which is another name for a [request handler](https://github.com/php-fig/fig-standards/blob/master/accepted/PSR-15-request-handlers.md#21-psrhttpserverrequesthandlerinterface))
    * the dispatcher loads the __middleware queue__ from the MIDDLEWARE_QUEUE_PATH constant value (defaults to _private/share/packagist/vukbgit/simplex/config/middleware.php_), Simplex default queue is composed by:
        * the __Router__ which loads routes definitions from any file named "routes.php" stored under the _private/local/simplex_ folder (even in subdirectories); the route definition must contain an "action" parameter (_private/local/simplex/config/route.php_ contains more details about routes definitions)
        * the Simplex __Authentication__ middleware that:
            * fires conditionally if an "authentication" parameter is found inside the current route definition
            * if fired checks whether the user is currently authenticated, otherwise redirects to a configured url
        * the __Request Handler__ (no, not the __Dispatcher__ from above, there is a bit of naming confusion on this matter...), which is responsible for processing the current route, invokes the __Route Handler__ (a local class) specified into the route definition which must inherit from one of the Simplex\Controller abstract classes
        * the __Route Handler__:
            * stores all of the request parameters and the response object into class properties
            * calls a method named after the "action" route parameter
            * this method performs all the tasks needed by the action and usually renders a template injecting HTML code into the response
        * the __Dispatcher__ returns the response to the _index.php_ scope
    * the HTTP status code of the response is checked and if different from 200 (which means "everything went fine") gets the appropriate HTML code from a _private/share/packagist/vukbgit/simplex/src/errors/_ file and injects it into the response
    * the __Emitter__ is instantiated and returns the response to the browser

## Folders and Files Structure ##

Simplex extends the classes namespace logic to every file in the application;: the __local namespace__ starts from the folder defined into _private/local/simplex/config/constants.php_ LOCAL_DIR constant (defaults to _private/local/simplex_) and is named by default _Simplex\Local_.

Into this folder the classes are arranged as the typical application, by business domain logic (i.e. the _News_ folder for all classes related to news, the _Customer_ folder, etc). But also every other file with different purpose (configuration files, html templates, SASS files...) should follow this logic; so there is no grouping by function first (a top _config_ folder, a top _views_ folder, etc.), but instead by namespace/business logic first (so _/News/config_ and _News/templates_ folders).

This is because typically application development proceeds by domain logic: adding the News functionality means adding at least a News class, some News configuration (routes and DI container definitions) and some News views (HTML templates for backend and frontend); if all of these files are scattered through local folder subfolders I find it harder to develope,  mantain and "clone" functionalities to be used as draft for new ones

So here are folders and files as installed from Simplex, from the installation root folder:

* __private__: all files that CANNOT be directly accessed by browser
    * __local__: files developed for the application
        * __simplex__: top level namespace folder for application files, every class defined inside has base namespace _Simplex\Local_
            * __Backend__: backend namespace draft folder
            * __Frontend__: frontend namespace draft folder
            * __bin__: created at installation time for useful bash scripts
                * __composer.sh__: allows to use composer with a PHP version different from the system default one used by the PHP CLI application, useful on a system with multiple PHP versions installed; it's a good idea to soft link it into root
            * __config__: configuration files for whole application to be customized
                * __constants.php__: environment constants, quite self explanatory, some of them should be set right after installation; NOTE: most of the regards paths Simplex uses for inclusions, it shouldn't be necessary to change them; if so beware that a pair of paths are hard coded into _index.php_ prior to including this file and should be changed manually
                * __db.php__: database configuration, returns a PHP object, gets configuration from _config.ini_
                * __di-container.php__: definition to be used by the DI Container to instantiate the classes used by the application; it integrates __private/local/share/vukbgit/simplex/src/config/di-container.php/__ which stores the definitions for classes used by the Simplex engine
                * __languages.json__: languages used by the application, indexed by a custom key (the one proposed is the ISO-639-1 two letters code); if the route passes a "language" parameter, language is searched for otherwise first one defined (defaults to English) it's used
                * __sass.config__: custom format file to speed up Sass files compilation using the _sass.sh_ script: you can define for each file to be compiled a custom id (any string) and source and destination paths, so you you ca use the shell and call from the root folder `sass file-id` to compile the minified CSS version
            * __sass__: some scss empty drafts to help compile Bootstrap and some application css
                * __application.scss__: rules for the whole application
                * __bootstrap-variables.scss__: it is included BEFORE the file with the _variables.scss_ shipped with Bootstrap to override Bootstrap built-in variables
                * __bootstrap.scss__: main file to compile Bootstrap css, includes only the most commonly used components, uncomment lines to include other functionalities; _private/local/simplex/config/sass.config_ already contains configuration to compile this file by means of the root _sass.sh_ file, just executing in the shell  './sass.sh bs'
                * __functions.scss__: definitions for some useful Sass functions
                * __variables.scss__: Sass variables to be used by the application
            * __templates__: some ready to use and customize Twig templates
    * __share__: files installed through Composer and possibly other third-part libraries from other sources
        * __packagist__: libraries installed by means of [Packagist](https://packagist.org)
          * __vukbgit__
              * __simplex__: shared Simplex modules used by application, some explanations about the less obvious ones:
                  * __bin__: bash scripts, some of the soft linked into root at installation composer project creation time
                  * __installation__: folders and files copied at installation time ready to be used and/or to be customized
                  * __src__: classes and other files used by Simplex at runtime
                      * __config__: configuration files
                          * __di-container.php__: definition to be used by the DI Container to instantiate the classes used by the Simplex engine; it is integrated by ANY file with the same name found under the __private/local/simplex__ folder
                          * __middleware.php__: middleware queue to be processed by the Dispatcher, can be overridden setting _MIDDLEWARE_QUEUE_PATH_ value into _private\local\simplex\config\constants.php_
                      * __errors__: HTML files to be displayed in case of HTTP errors raised by the request
                      * __templates__: ready to use Twig templates for backend areas with CRUDL functionalities
          * all the other Composer libraries used by the application
* __public__: all files that CAN be accessed by browser
    * __local__: files developed for the application such as compiled css files and javascript files
    * __share__: libraries installed from npm and any other third-part frontend javascript and css asset
        * __package.json__: npm configuration file, requires Bootstrap and jQuery latest plus other useful libraries, customize at need
    * __.htaccess__: redirects ALL requests beginning with "public/" to _index.php_ except the ones for files really existing into filesystem (css, js, etc.)
* __.gitignore__: in a development/production flow I commit file to a private repository form the development site and pull them into the production one; this .gitignore file excludes some Simplex folders/files from commit
* __.htaccess__: root Apache directives
    * redirects ALL requests for the root directory to public/index.php
* __composer.json__:
    * sets vendor directory to _private/share/packagist_
    * sets bin directory to _./_ so that symlinks are created into root for some shell scripts
    * sets autoload application directory to _private/local/simplex_ mapping this path to _Simplex\Local_ namespace
    * requires the Simplex package (which takes care of requiring the other needed packages)
* __index.php__: application bootstrap file, since it is stored into site root all PHP includes in every file work with absolute path form site root, see "Application Flow" above for details
* __sass.sh__: soft link to the helper script _private/share/packagist/vukbgit/simplex/bin/sass.sh_ to compile Sass files, see the _private/local/simplex/config/sass.config_ explanation above for details
* __npm.sh__: soft link to the helper script _public/share/npm.sh_ to manage npm packages into _public/share_ folder, call it `./npm.sh npm-command`, i.e `./npm.sh install foolibrary` to perform the installation into _local/share/node_modules/foolibrary_

## Migration from v2 to v3 ##

* index.php: Zend -> Laminas
*  FILTER_SANITIZE_STRING -> FILTER_SANITIZE_SPECIAL_CHARS:
  * grep -rl FILTER_SANITIZE_STRING private/local/simplex | xargs sed -i 's/FILTER_SANITIZE_STRING/FILTER_SANITIZE_SPECIAL_CHARS/'
* ERP:
  * list:{% block rows %} {% for record in records %}<tr> {{ tableMacros.displayRowBegin(_context, loop, record) }} <td>{{ record.FIELD }}</td> {{ tableMacros.displayRowEnd(_context, loop, record) }}</tr> {% endfor %}{% endblock %}
  * crudl-form:
    * dateTimePickers bound for a date range now unified
  * css:
    * ERP style no more directly included but integrated into area (i.e. Backend) scss file:
      * create area scc file (copy from installation folder), i.e. _private/local/simplex/Backend/sass/backend.css_
      * compile it, i.e. `./sass.sh be`
      * include into area template, i.e. _private/local/simplex/Backend/templates/backend.twig_
* FORMS:
  * alla captchas dependencies and functionalities removed, implement honey trap technique
  * update (copy from installation folder) calendar dates format for languages into _private/local/simplex/config/languages.json_
* TWIG:
  * move if conditions out of for loops, regular expression to search for them: `grep -rE "{%\s+for\s+[a-zA-Z ,]+\s+in\s+[a-zA-Z\.]+\s+if" private/local`
* SASS:
  * enclose arithmetic operations into `calc()` function
* BOOTSTRAP 5:
  * .ml-* and .mr-* -> .ms-* and .me-*.
  * .sr-only -> .visually-hidden
  * form grid: form-row -> row
  * .form-inline: use grid row > col / col-md-auto
  * all af _data-*_ attributes in collapse (i.e. menu voices), carousels, etc become _data-bs-*_
  * text-alignment: -left -> -start; right->end
  * remove input-group-append / prepend
  * close buttons (modal, alert...):
    * class close -> btn-close
    * data-dismiss -> data-bs-dismiss
    * remove inner span
* SPREADSHEET READER/WRITER: reader moved to phpoffice/phpspreadsheet should be fine, __writer implementation currently still broken!__

## Considerations ##

* I choose not to use any framework because I want to be 100% in control of the flow inside the application
* Simplex uses third party classes for almost every specialized task (DI container, routing, dispatching, emitting...)
* I coded some components into Simplex only when I couldn't find an external library to accomplish some task the way I needed: for example I wrote the nikic/fastroute middleware to be able to pass custom route parameters
* design choices: I tried to search documentation, mostly seeking "no framework" suggestions (see references below), and taking a look to existing frameworks (although I am no expert in this field because I started structuring my code for re-use since 2000); I want Simplex to be up-to-date but also to be, well, simple and there is no agreement on every topic, for example [the use of a DI Container](https://hackernoon.com/you-dont-need-a-dependency-injection-container-10a5d4a5f878). Therefore I made my (very questionable) choices, keeping __always__ in mind the I needed a tool to build web applications in the fastest and most flexible way
* So I ended up with a framework myself?! Honestly I do not know

## API Documentation ##

API dcoumentation generated with phpDocumentor can be found at [https://vukbgit.github.io/simplex]
 
## References ##

* [https://github.com/PatrickLouys/no-framework-tutorial]
* [https://kevinsmith.io/modern-php-without-a-framework#properly-sending-responses]
