<?php

declare(strict_types=1);

/*
 * routes/web.php
 *
 * Contains only the Breeze authentication routes.
 * All other routes (Dashboard, Admin) are registered by individual modules
 * via their ServiceProvider's loadRoutes() method.
 */

require __DIR__ . '/auth.php';
