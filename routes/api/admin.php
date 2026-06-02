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
    'board',
] as $routeFile) {
    require base_path("routes/api/admin/{$routeFile}.php");
}
