#Installation#

##Put in the right location##

Unzip and copy the aaf folder to the `/var/www/example/plugins/authentication/` folder.

##Discover and enable##

Once in place, you need to log in as the HZCMS administrator and then discover and enable the module.

* Go to https://130.56.249.56/administrator/  and login
* Then to 'Extensions' -> 'Extensions Manager'
* Select the 'Discover' tab
* Hit the 'Discover' button
* Enable the AAF module that is now revealed.

##Add the CSS##

The css file needs to be appended to the providers.css file:

```bash
cat /var/www/example/plugins/authentication/aaf/assets/aaf.css >> /var/www/example/components/com_users/assets/css/providers.css
```

##Patch##

The patch for the file: `components/com_users/controllers/user.php` needs to be applied.

To apply it issue the following command:

```bash
patch /var/www/example/components/com_users/controllers/user.php < /var/www/example/plugins/authentication/aaf/assets/user.patch 
```

#Notes#

If you want to add some logging in your php file, do something along the lines of:

```php
error_log(print_r(JRequest::get(),true),3,"/tmp/hzm.log");
```

