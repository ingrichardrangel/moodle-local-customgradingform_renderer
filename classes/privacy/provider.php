<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

/**
 * Privacy Subsystem implementation for local_customgradingform_renderer.
 *
 * @package   local_customgradingform_renderer
 * @category  privacy
 * @copyright 2025 Richard Rangel
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace local_customgradingform_renderer\privacy;

use core_privacy\local\metadata\null_provider;

/**
 * Null provider since this plugin does not store personal user data.
 */
class provider implements null_provider {

    /**
     * Returns a language string explaining why this plugin stores no data.
     *
     * @return string
     */
    public static function get_reason(): string {
        return get_string('privacy:metadata', 'local_customgradingform_renderer');
    }
}