<?php
// HiveMQ Bridge via Mosquitto with proper TLS SNI
function publishToHiveMQWithTLS($topic, $message) {
    $host = 'f298176d58fd4963bf36cf28d6439be7.s1.eu.hivemq.cloud';
    $port = 8883;
    $username = 'user2';  
    $password = 'Kwdikos(kwdikos)123';
    
    // Command with proper TLS SNI support
    $cmd = sprintf(
        'mosquitto_pub -h %s -p %d -u %s -P %s -t %s -m %s --tls-version tlsv1.2 --capath /etc/ssl/certs --insecure',
        escapeshellarg($host),
        $port,
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($topic),
        escapeshellarg($message)
    );
    
    echo "🔧 Executing: " . $cmd . "\n";
    
    exec($cmd, $output, $return_var);
    
    if ($return_var === 0) {
        echo "✅ Published successfully via Mosquitto with TLS SNI\n";
        return true;
    } else {
        echo "❌ Publish failed. Return code: {$return_var}\n";
        echo "📝 Output: " . implode("\n", $output) . "\n";
        return false;
    }
}

// Enhanced function with better error handling
function publishToHiveMQSecure($topic, $message) {
    $host = 'f298176d58fd4963bf36cf28d6439be7.s1.eu.hivemq.cloud';
    $port = 8883;
    $username = 'user2';  
    $password = 'Kwdikos(kwdikos)123';
    
    // Try secure connection first
    $cmd_secure = sprintf(
        'mosquitto_pub -h %s -p %d -u %s -P %s -t %s -m %s --tls-version tlsv1.2 --capath /etc/ssl/certs 2>&1',
        escapeshellarg($host),
        $port,
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($topic),
        escapeshellarg($message)
    );
    
    echo "🔒 Trying secure TLS connection...\n";
    exec($cmd_secure, $output_secure, $return_secure);
    
    if ($return_secure === 0) {
        echo "✅ Published successfully with secure TLS verification\n";
        return true;
    }
    
    echo "⚠️ Secure connection failed, trying insecure mode...\n";
    echo "📝 Secure error: " . implode("\n", $output_secure) . "\n";
    
    // Fall back to insecure mode
    $cmd_insecure = sprintf(
        'mosquitto_pub -h %s -p %d -u %s -P %s -t %s -m %s --tls-version tlsv1.2 --capath /etc/ssl/certs --insecure 2>&1',
        escapeshellarg($host),
        $port,
        escapeshellarg($username),
        escapeshellarg($password),
        escapeshellarg($topic),
        escapeshellarg($message)
    );
    
    exec($cmd_insecure, $output_insecure, $return_insecure);
    
    if ($return_insecure === 0) {
        echo "✅ Published successfully with insecure TLS mode\n";
        return true;
    } else {
        echo "❌ Both secure and insecure modes failed\n";
        echo "📝 Insecure error: " . implode("\n", $output_insecure) . "\n";
        return false;
    }
}

// Test both functions
echo "=== Testing HiveMQ Cloud with Mosquitto TLS SNI ===\n\n";

echo "📡 Test 1: Basic TLS with insecure mode\n";
if (publishToHiveMQWithTLS('test/php-basic', 'Hello from Laravel Basic!')) {
    echo "🎉 Basic TLS test passed!\n";
} else {
    echo "💥 Basic TLS test failed!\n";
}

echo "\n📡 Test 2: Enhanced TLS with fallback\n";
if (publishToHiveMQSecure('test/php-enhanced', 'Hello from Laravel Enhanced!')) {
    echo "🎉 Enhanced TLS test passed!\n";
} else {
    echo "💥 Enhanced TLS test failed!\n";
}

echo "\n=== Test Complete ===\n";
?>
