<?php
  
function active_class($path, $active = 'active') {
    return request()->is(...(array)$path) ? $active : '';
}

function is_active_route($path) {
    return request()->is(...(array)$path) ? 'true' : 'false';
}

function show_class($path) {
    return request()->is(...(array)$path) ? 'show' : '';
}