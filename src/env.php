<?php
// Simple environment variable loader for .env files
(function() {
    $envPath = dirname(__DIR__) . '/.env';
    if (!file_exists($envPath)) {
        return;
    }
    
    $lines = file($envPath, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        // Skip comments and lines without assignments
        if ($line === '' || strpos($line, '#') === 0 || strpos($line, '=') === false) {
            continue;
        }
        
        // Split at the first '=' to allow for values containing '='
        $parts = explode('=', $line, 2);
        if (count($parts) === 2) {
            $key = trim($parts[0]);
            $val = trim($parts[1]);
            
            // Strip potential enclosing quotes
            if (preg_match('/^"([^"]*)"$/', $val, $matches) || preg_match('/^\'([^\']*)\'$/', $val, $matches)) {
                $val = $matches[1];
            }
            
            // Put into environment, $_ENV, and $_SERVER
            putenv("{$key}={$val}");
            $_ENV[$key] = $val;
            $_SERVER[$key] = $val;
        }
    }
})();
