@echo off
echo Starting MQTT Polling Listener...
echo This will poll MQTT devices every 10 seconds without maintaining persistent connections
echo Press Ctrl+C to stop
echo.
php artisan mqtt:poll --interval=10 --verbose
