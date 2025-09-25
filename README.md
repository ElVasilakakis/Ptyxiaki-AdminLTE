git clone https://github.com/ElVasilakakis/Ptyxiaki-AdminLTE
cd Ptyxiaki-AdminLTE

# Install dependencies
composer install

# Environment setup
cp .env.example .env
php artisan key:generate

# Database setup
php artisan migrate

# Start web server
php artisan serve


# MQTT Listener
php artisan mqtt:listen-all --device-reload-interval=60

# Queue Worker
php artisan queue:work --queue=mqtt