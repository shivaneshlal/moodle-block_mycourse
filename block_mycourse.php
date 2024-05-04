<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.
/**
 * Block mycourse is defined here.
 *
 * @package     block_mycourse
 * @copyright   2024 Shivanesh Lal<shivanesh.lal@outlook.com.com>
 * @license     https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 *
 * This is the My Course block class.
 *
 * This class extends the base block class and provides functionality
 * for displaying a user's courses by years.
 */
class block_mycourse extends block_base {

    /**
     * Initializes the block.
     *
     * This method is called to set the title of the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_mycourse');
    }

    /**
     * Defines the block's configuration.
     *
     * This method is called to define the block's configuration.
     *
     * @return bool True.
     */
    public function has_config() {
        return true;
    }

    /**
     * Returns the content of the block.
     *
     * This method is called to generate the content of the block.
     *
     * @return stdClass The content of the block.
     */
    public function get_content() {

        global $OUTPUT, $CFG, $USER, $DB;

        if ($this->content !== null) {
            return $this->content;
        }

        // Get Current year to build year tabs.
        $currentyear = date('Y');

        $this->content = new stdClass;
        $this->content->text = '';
        $this->content->footer = '';

        $mycoursecontents = new stdClass();
        $mycoursecontents->mycoursecontents = $this->block_mycourse_build_nav_tabs($USER->id, $currentyear);

        // Render the block content.
        $this->content->text = $OUTPUT->render_from_template('block_mycourse/mycourse', $mycoursecontents);
        return $this->content;
    }

    /**
     * Builds the navigation tabs for the block.
     *
     * This method is called to generate the navigation tabs for the block.
     *
     * @param int $userid The ID of the user.
     * @param int $currentyear The current year.
     * @return array The navigation tabs.
     */
    public function block_mycourse_build_nav_tabs($userid, $currentyear) {
        $mycoursecontents = [];
        $tablastyear = get_config('block_mycourse', 'year');
        for ($tab = $currentyear + 1; $tab >= $tablastyear; $tab --) {

            // Get all courses for the user for the year.
            $usercourses = $this->block_mycourse_get_usercourses($userid, $tab);

            $mycoursecontents[] = [
                'year' => $tab,
                'active' => ($tab == $currentyear),
                'courses' => $usercourses,
                'nocourse' => get_string('nocourse', 'block_mycourse'),
            ];
        }
        return $mycoursecontents;
    }

    /**
     * Gets the courses for the user.
     *
     * This method is called to get the courses for the user.
     *
     * @param int $userid The ID of the user.
     * @param int $year The year.
     * @return array The courses for the user.
     */
    public function block_mycourse_get_usercourses($userid, $year) {

        // Get all courses that the user is enrolled in.
        $usercourses = enrol_get_users_courses($userid, true, null, 'startdate DESC');
        $usercourse = [];

        foreach ($usercourses as $course) {

            // Get the user roles for the course.
            $context = context_course::instance($course->id);
            $userroles = get_user_roles($context, $userid);
            $userrole = reset($userroles);
            $startyear = date('Y', $course->startdate);

            if ($startyear == $year) {

                // Check if the course is visible or if the user is a teacher.
                if ($course->visible == 1 || ($course->visible == 0 && $userrole->roleid <= 4)) {
                    $usercourse[] = $course;
                }
            }
        }
        return $usercourse;
    }
}
