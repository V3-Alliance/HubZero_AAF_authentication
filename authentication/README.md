#Installation#

##Put in the right location##

Unzip and copy the aaf folder to the `/var/www/example/plugins/authentication/` folder.

##Discover and enable##

Once in place, you need to log in as the HZCMS administrator and then discover and enable the module.

* Go to https://<site_address>/administrator/  and login
* Then to 'Extensions' -> 'Extensions Manager'
* Select the 'Discover' tab
* Hit the 'Discover' button
* Enable the AAF module that is now revealed.

##Integration##

* Register your service - eg. [Rapid Connect](https://rapid.aaf.edu.au/)
* Set the callback URL to https://YOUR_DOMAIN/index.php?option=com_users&task=user.login&authenticator=aaf
* Note down the secret somewhere secure
  * NB: If you secret key contains the less than symbol "<" your key will get escaped when saving it in the Admin Plugin Manager.
  * E.g: "fo0<B@r" will become "fo0" after you click save
* Note down the URL provided by Rapid Connect once registered
* Open the plugin 'Authentication - AAF' via the plugin manager and edit the following properties
  * Set the status to 'Enabled'
  * Set the Consumer Secret with the secret set previously when registering your service with Rapid Connect
  * Set the AAF Issuer URL - eg. https://rapid.aaf.edu.au
  * Set the AAF Login URL to the URL provided previously by Rapid Connect when registering your service

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

