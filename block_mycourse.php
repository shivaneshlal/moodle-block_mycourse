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
                    // Get the course category.
                    $category = $this->block_mycourse_get_course_categories($course->id);
                    // Get course image.
                    $image = $this->block_mycourse_get_course_image($course->id);
                    // Get Course visibility.
                    $coursestatus = "";
                    if ($course->visible == 0 && ($userrole->roleid <= 4)) {
                        $coursestatus = get_string('coursestatus', 'block_mycourse');
                    }
                    // Get course completion percentage for students only.
                    $coursecompletion = null;
                    if ($userrole->roleid == 5) {
                        $coursecompletion = $this->get_course_completion($course, $userid);
                    }

                    $usercourse[] = [
                        'course' => $course,
                        'category' => $category,
                        'image' => $image,
                        'status' => $coursestatus,
                        'progress' => $coursecompletion,
                    ];
                }
            }
        }
        return $usercourse;
    }

    /**
     * Gets the course categories.
     *
     * This method is called to get the course categories.
     *
     * @param int $courseid The ID of the course.
     * @return string The course category.
     */
    public function block_mycourse_get_course_categories($courseid) {
        global $DB;

        // Check if the Coursecategory setting is enabled.
        if (!get_config('block_mycourse', 'coursecategory')) {
            return null;
        }

        $category = '';
        $course = $DB->get_record('course', ['id' => $courseid], '*', MUST_EXIST);
        $category = $DB->get_field('course_categories', 'name', ['id' => $course->category], MUST_EXIST);
        return $category;
    }

    /**
     * Gets the course image.
     *
     * This method is called to get the course image.
     *
     * @param int $courseid The ID of the course.
     * @return string The course image URL.
     */
    public function block_mycourse_get_course_image($courseid) {

        // Check if the Courseimage setting is enabled.
        if (!get_config('block_mycourse', 'courseimage')) {
            return null;
        }

        $course = get_course($courseid);
        $context = context_course::instance($courseid);

        // Get course overview files.
        $fs = get_file_storage();
        $files = $fs->get_area_files($context->id, 'course', 'overviewfiles', 0, 'sortorder DESC, id ASC', false);

        if (count($files) > 0) {
            // Get the first file.
            $file = reset($files);
            $url = moodle_url::make_pluginfile_url(
                $file->get_contextid(),
                $file->get_component(),
                $file->get_filearea(),
                null,
                $file->get_filepath(),
                $file->get_filename()
            );
            $courseimage = $url->out();
        } else {
            // Genereate a random image.
            $courseimage = $this->generaterandomsvg();
        }

        return $courseimage;
    }

    /**
     * Generates a random SVG image.
     *
     * This method is called to generate a random SVG image.
     *
     * @return string The random SVG image.
     */
    public function generaterandomsvg() {
        $color = $this->generaterandomcolor();
        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320">
            <rect x="0" y="0" width="320" height="320" fill="' . $color . '"/>';

        // Generate a pattern of circles with varying opacity.
        for ($i = 0; $i < 10; $i++) {
            for ($j = 0; $j < 10; $j++) {
                $opacity = (mt_rand(1, 5) / 10);
                $svg .= '<circle cx="' . ($i * 32) . '" cy="' . ($j * 32) . '" r="16" fill="white" opacity="' . $opacity . '"/>';
            }
        }

        $svg .= '</svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Generates a random color.
     *
     * This method is called to generate a random color.
     *
     * @return string The random color.
     */
    public function generaterandomcolor() {
        return 'rgb(' . mt_rand(0, 200) . ',' . mt_rand(0, 200) . ',' . mt_rand(0, 200) . ')';
    }

    /**
     * Gets the course completion percentage.
     *
     * This method is called to get the course completion percentage.
     *
     * @param stdClass $course The course object.
     * @param int $userid The ID of the user.
     * @return float|null The course completion percentage, or null if no completion data is available.
     */
    public function get_course_completion($course, $userid) {
        global $CFG;
        require_once($CFG->dirroot . '/completion/classes/progress.php');
        require_once($CFG->libdir . '/completionlib.php');

        // Create an instance of completion_info for the given course.
        $completion = new \completion_info($course);

        // Check if completion is enabled for this course.
        if (!$completion->is_enabled()) {
            return false;
        }

        // Get the course progress percentage.
        $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);

        return $progress;
    }

}
