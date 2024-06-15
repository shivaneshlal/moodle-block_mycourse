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
 * Version metadata for the block_mycourse plugin.
 *
 * @package   block_mycourse
 * @copyright 2024, shivanesh lal
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


 defined('MOODLE_INTERNAL') || die;

 // Set default yearto look back 5 years.
 $currentyear = date('Y');
 $defaultyear = $currentyear - 5;

if ($ADMIN->fulltree) {
    // Create settings page.
    $settings->add( new admin_setting_configtext(
        'block_mycourse/year',
        get_string('pluginname', 'block_mycourse'),
        get_string('configyear', 'block_mycourse'),
        "$defaultyear",
        PARAM_INT,
        4,
     ));
    // Course image display setting checkbox.
    $settings->add( new admin_setting_configcheckbox(
        'block_mycourse/courseimage',
        get_string('pluginname', 'block_mycourse'),
        get_string('Courseimage', 'block_mycourse'),
        0
    ));

}
