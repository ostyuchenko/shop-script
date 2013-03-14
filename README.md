# Shop-Script #

Shop-Script is a robust PHP shopping cart software developed using the Webasyst Framework.
Distributed under the terms of LGPL license.
http://www.shop-script.com/
http://www.webasyst.com/

## System Requirements ##

  * Web Server
		* e.g. Apache or IIS
		
	* PHP 5.2+
		* spl extension
		* mbstring
		* iconv
		* json
		* gd or ImageMagick extension

	* MySQL 4.1+

## Installing Webasyst Framework ##

Install Webasyst Framework via http://github.com/webasyst/webasyst-framework/ or http://www.webasyst.com/framework/

## Installing Shop-Script ##

1. Once Webasyst Framework is installed, get Shop-Script code into your /PATH_TO_WEBASYST/wa-apps/shop/ folder:

	via GIT:

		cd /PATH_TO_WEBASYST/wa-apps/shop/
		git clone git://github.com/webasyst/webasyst-framework.git

	via SVN:
	
		cd /PATH_TO_WEBASYST/wa-apps/shop/
		svn checkout http://svn.github.com/webasyst/webasyst-framework.git

2. Add the following line into the /wa-config/apps.php file (this file lists all installed apps):

		'shop' => true,
		
3. Done. Run Webasyst Framework in a web browser (e.g. http://localhost/webasyst/).

## Updating Webasyst Framework ##

Staying with the latest version of Webasyst Framework and Shop-Script is easy: simply update your files from the repository and login into Webasyst, and all required meta updates will be applied to Webasyst and its apps automatically.
