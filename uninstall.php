<?php

// Check for valid uninstall request
if (!defined('WP_UNINSTALL_PLUGIN')) {
    exit;
}

// Delete img.vision options
delete_option('img-vision-api-key');
delete_option('img-vision-property-id');
