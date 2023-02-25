# JetBrains Time Tracking for ClickUp
Enable Time Tracking of JetBrains (PHPStorm, WebStorm) with Clickup (using Gitlab endpoints).

This is some kind of "workaround" to track time directly from PHP Storm to ClickUp. We are using native JetBrains integration with Gitlab and rewrite them for ClickUp.

## Reqiurements
- PHP >= 7.4 (not tested with PHP8.x, but code is comptabile)
- Apache >= 2.4 (not tested with nginx)

## Apache vhost configuration
In Virtual Host `AllowEncodedSlashes` need to be enabled.
Puth this line between `<VirualHost>` and `</VirtualHost>`

Example for vhost file.
```
<VirtualHost *:80>
    ServerName track.example.com
    DocumentRoot /var/www/vhosts/track
    ErrorLog /var/log/httpd/track.example.com-error_log
    CustomLog /var/log/httpd/track.example.com-access_log combined
    HostnameLookups Off
    UseCanonicalName On
    ServerSignature Off

    <FilesMatch \.php$>
        SetHandler "proxy:unix:/var/php-fpm/www.sock|fcgi://localhost:9000"
    </FilesMatch>

    AllowEncodedSlashes NoDecode
    
    <Directory "/var/www/vhosts/track">    
        Options FollowSymLinks
        AllowOverride All
        Require all granted
    </Directory>
</VirtualHost>
```

## PHP Storm Configuration
### Configure Tracking Server
1. Go to `File Settings (Ctrl + Alt + S) - Tasks - Servers`.
2. Click on `+` and choose **Gitlab** Server.

![image](https://user-images.githubusercontent.com/1854269/221335335-3323e7b6-5fea-4188-bb1e-f0ee9f0746e4.png)

4. Add server address configured above (http://track.example.com).
5. Put **ClickUp** API Key (Menu - Integrations - Custom Apps). API Key starts with "pk_".
6. Choose Project (ClickUp Space) from which will be fetched tasks.

![image](https://user-images.githubusercontent.com/1854269/221335367-3038ab14-ce3e-4dc6-8d4c-791763ae6bbb.png)

### Enable Time Tracking
1. Go to `File - Settings (Ctrl + Alt + S) - Tasks - Time Tracking`.
2. Check Enable Time Traking.

![image](https://user-images.githubusercontent.com/1854269/221335381-9e88452e-b326-47fa-8d80-9da67a46ffb4.png)

### Track Time
When Time Tracking feature is enabled you will see additional window form which you can get list of tasks.

## Known issues
- Current version not works with Folders in ClickUp Spaces.
- Comments from Tracking windows are not added to ClickUp.

# Have a nice Tracking :)

### Disclaimer
This was created for internal purposes and probably will not cover all cases. Feel free to open issue or fork and modify the code for your own requirements.
