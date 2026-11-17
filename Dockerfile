FROM php:8.2-apache

# ติดตั้ง extensions ที่จำเป็นสำหรับจัดการ MySQL ภาพ และ Word/PDF
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libfreetype6-dev \
    libzip-dev \
    libreoffice \
    fonts-thai-tlwg \
    && docker-php-ext-configure gd --with-freetype --with-jpeg \
    && docker-php-ext-install -j$(nproc) gd pdo pdo_mysql mysqli zip \
    && rm -rf /var/lib/apt/lists/*

# เปิดใช้งาน Apache Rewrite Module
RUN a2enmod rewrite

# คัดลอกไฟล์โปรเจกต์ทั้งหมดเข้า Container
COPY . /var/www/html/

# สร้างโฟลเดอร์สำหรับไฟล์ที่ระบบต้องเขียน
RUN mkdir -p /var/www/html/uploads \
    /var/www/html/tmp

# ปรับ Permissions ให้ Apache อ่านและเขียนไฟล์ได้
RUN chown -R www-data:www-data /var/www/html/uploads \
    /var/www/html/tmp \
    && chmod -R 775 /var/www/html/uploads \
    /var/www/html/tmp