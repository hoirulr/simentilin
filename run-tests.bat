@echo off
cd /d d:\laragon\www\simentilin
php artisan migrate:fresh --seed
echo.
echo === Running Tests ===
echo.
php artisan test
