<?php

use Illuminate\Support\Facades\Route;

/*
 * routes/web.php
 *
 * Enthält nur noch die Breeze Auth-Routes.
 * Alle anderen Routes (Dashboard, Admin) werden von den Modulen registriert.
 */

require __DIR__ . '/auth.php';
