<VirtualHost *:80>
    ServerName medekform.loc
    ServerAdmin sametatabasch@gmail.com
    DocumentRoot "__ROOT__/public"

    <Directory "__ROOT__/public">
        Options -Indexes +FollowSymLinks
        AllowOverride All
        Require all granted
        DirectoryIndex index.php
        FallbackResource /index.php
    </Directory>
    <Directory "__ROOT__/data">
        Require all denied
    </Directory>

    # Dosya yükleme sınırları (şablon + kanıt dosyaları)
    php_admin_value upload_max_filesize 32M
    php_admin_value post_max_size 40M
    php_admin_value max_file_uploads 20
    php_admin_value session.gc_maxlifetime 28800

    ErrorLog ${APACHE_LOG_DIR}/medekform_error.log
    CustomLog ${APACHE_LOG_DIR}/medekform_access.log combined
</VirtualHost>
