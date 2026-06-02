<?php

foreach ([
    'condominiums',
    'houses',
    'billing',
    'residents',
    'catalogs',
    'security',
    'menus',
    'audit',
] as $routeFile) {
    require base_path("routes/api/admin/{$routeFile}.php");
}
