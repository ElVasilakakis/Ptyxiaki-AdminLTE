@echo off
echo Starting Laravel IoT System...
echo.

echo Starting PHP Development Server...
start "PHP Server" cmd /k "php -S 127.0.0.1:8000 -t public"

echo Waiting 3 seconds for server to start...
timeout /t 3 /nobreak >nul

echo Starting LoRaWAN Listener...
start "LoRaWAN Listener" cmd /k "php artisan lorawan:listen"

echo Starting Universal Device Listener...
start "Device Listener" cmd /k "php artisan devices:listen"

echo.
echo ========================================
echo All services started successfully!
echo ========================================
echo.
echo Services running:
echo - PHP Server: http://127.0.0.1:8000
echo - LoRaWAN Listener: Listening for LoRaWAN devices
echo - Device Listener: Listening for all device types
echo.
echo Press any key to close this window...
echo (Services will continue running in separate windows)
pause >nul
