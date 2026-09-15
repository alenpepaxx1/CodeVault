<?php
/**
 * CodeVault development router.
 * Copyright (c) 2026 Alen Pepa. All rights reserved.
 */
declare(strict_types=1);
$path = rawurldecode((string)parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH));
if (str_starts_with($path, '/data/') || str_contains($path, '/.') || preg_match('/\.(sqlite|db|log)$/i', $path)) {
    http_response_code(404); exit('Not found');
}
return false;
