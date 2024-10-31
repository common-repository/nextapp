<?php
/**
 * @package NextApp
 */
/*
Plugin Name: NextApp
Description: NextApp interface is blogging web site to provide mobile phone client used to access the blog classification, the article and comment data interface.
Version: 1.0.3
Author: Daye
License: GPLv2 or later
*/

/*
This program is free software; you can redistribute it and/or
modify it under the terms of the GNU General Public License
as published by the Free Software Foundation; either version 2
of the License, or (at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.
*/

define('NEXTAPP_VERSION', '1.0.3');
define('NEXTAPP_PATH', dirname(__FILE__));

require NEXTAPP_PATH . DIRECTORY_SEPARATOR . 'library' . DIRECTORY_SEPARATOR . 'NextApp.php';
NextApp::singleton()->run();
