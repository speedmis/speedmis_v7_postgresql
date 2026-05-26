<?php
header('Content-Type: text/plain');
echo "OK\n";
if (function_exists('opcache_reset')) {
    opcache_reset();
    echo "opcache reset\n";
}
