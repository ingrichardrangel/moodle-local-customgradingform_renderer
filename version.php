<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Plugin version and metadata.
 *
 * @package   local_customgradingform_renderer
 * @copyright Richard Rangel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

$plugin->component = 'local_customgradingform_renderer'; // Full name of the plugin (used for diagnostics).
$plugin->version   = 2025051800; // Plugin version (YYYYMMDDXX).
$plugin->requires  = 2022041900; // Minimum required Moodle version (Moodle 4.0).
$plugin->maturity = MATURITY_STABLE; // Development maturity level.
$plugin->release = '1.0.0'; // Human-readable release name.

$plugin->dependencies = []; // No other plugin dependencies.
