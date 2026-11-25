<?php
/**
 * CampusFix — Database Viewer Router
 * Seamlessly forwards /database/view_data.php requests to root /view_data.php
 */

$query = !empty($_SERVER['QUERY_STRING']) ? '?' . $_SERVER['QUERY_STRING'] : '';
header('Location: ../view_data.php' . $query);
exit;

