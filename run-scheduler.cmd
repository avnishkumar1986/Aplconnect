@echo off
cd /d D:\xampp\htdocs\aplconnect_react
D:\xampp\php\php.exe artisan schedule:run >> storage\logs\scheduler.log 2>&1
