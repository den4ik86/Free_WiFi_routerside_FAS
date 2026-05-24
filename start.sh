#!/bin/ash

# Создаём директории
mkdir -p /tmp/www/css
mkdir -p /tmp/www/js
mkdir -p /tmp/www/img/video
mkdir -p /tmp/www/img/logos
mkdir -p /tmp/www/img/banners

# Создаём символические ссылки (правильно!)
ln -sf /tmp/www/css /www/css
ln -sf /tmp/www/js /www/js
ln -sf /tmp/www/img /www/img

# Скачиваем JS файлы в /tmp/www/js/
wget -O /tmp/www/js/jquery.maskedinput.js http://10.200.10.1:8080/denis/js/jquery.maskedinput.js
wget -O /tmp/www/js/jquery.min.js http://10.200.10.1:8080/denis/js/jquery.min.js
wget -O /tmp/www/js/main.js http://10.200.10.1:8080/denis/js/main.js

# Скачиваем CSS файлы в /tmp/www/css/
wget -O /tmp/www/css/no_reg.css http://10.200.10.1:8080/denis/css/no_reg.css
wget -O /tmp/www/css/reg.css http://10.200.10.1:8080/denis/css/reg.css
wget -O /tmp/www/css/all.min.css http://10.200.10.1:8080/denis/css/all.min.css
wget -O /tmp/www/css/style.css http://10.200.10.1:8080/denis/css/style.css
wget -O /tmp/www/img/video/for_spot.mp4 http://10.200.10.1:8080/denis/img/video/for_spot.mp4
wget -O /tmp/www/img/banners/wifi_reklama.jpg http://10.200.10.1:8080/denis/img/banners/wifi_reklama.jpg
wget -O /tmp/www/img/logos/2.jpeg http://10.200.10.1:8080/denis/logos/2.jpeg
