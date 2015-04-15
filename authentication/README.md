##Patch##

The patch is for the file: `components/com_users/controllers/user.php`

##Notes##

To add some logging, do something along the lines of:

```php
error_log(print_r(JRequest::get(),true),3,"/tmp/hzm.log");
```

