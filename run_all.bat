@echo off
echo Starting Laravel IoT System...
echo.

echo Starting PHP Development Server...
start "PHP Server" cmd /k "php -S 127.0.0.1:8000 -t public"

echo Waiting 3 seconds for server to start...
timeout /t 3 /nobreak >nul

echo Starting MQTT Listener...
start "MQTT Listener" cmd /k "php artisan mqtt:listen"

echo.
echo ========================================
echo All services started successfully!
echo ========================================
echo.
echo Services running:
echo - PHP Server: http://127.0.0.1:8000
echo - MQTT Listener: Listening for MQTT devices
echo.
echo LoRaWAN Configuration:
echo - LoRaWAN devices use HTTP webhooks (not MQTT)
echo - Getting webhook URL for current environment...
php get_webhook_url.php
echo - Configure this URL in The Things Stack console
echo.
echo Press any key to close this window...
echo (Services will continue running in separate windows)
pause >nul
