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

        global $OUTPUT, $USER;

        if ($this->content !== null) {
            return $this->content;
        }

        // Get current year (user timezone) to build year tabs.
        $currentyear = (int)userdate(time(), '%Y');

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        $mycoursecontents = new stdClass();
        $mycoursecontents->instanceid = isset($this->instance->id) ? (int)$this->instance->id : 0;
        $mycoursecontents->mycoursecontents = $this->block_mycourse_build_nav_tabs($USER->id, $currentyear);
        $mycoursecontents->sortlabel = get_string('sortby', 'block_mycourse');
        $mycoursecontents->uncategorizedlabel = get_string('uncategorized', 'block_mycourse');
        $mycoursecontents->nofilteredcourseslabel = get_string('nofilteredcourses', 'block_mycourse');
        $mycoursecontents->expandalllabel = get_string('expandall', 'block_mycourse');
        $mycoursecontents->collapsealllabel = get_string('collapseall', 'block_mycourse');
        $mycoursecontents->togglecategorytemplatelabel = get_string('togglecategory', 'block_mycourse', '{category}');
        $mycoursecontents->searchlabel = get_string('searchcourses', 'block_mycourse');
        $mycoursecontents->searchplaceholder = get_string('searchcoursesplaceholder', 'block_mycourse');
        $mycoursecontents->showsearch = (bool)get_config('block_mycourse', 'coursesearch');
        $mycoursecontents->searchurl = (new moodle_url('/course/search.php'))->out(false);
        $mycoursecontents->sortoptions = [
            [
                'value' => 'category',
                'label' => get_string('sortbycategory', 'block_mycourse'),
            ],
            [
                'value' => 'recentlyaccessed',
                'label' => get_string('sortbyrecentlyaccessed', 'block_mycourse'),
            ],
            [
                'value' => 'startdate',
                'label' => get_string('sortbystartdate', 'block_mycourse'),
                'selected' => true,
            ],
        ];

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
        $tablastyear = (int)get_config('block_mycourse', 'year');
        if ($tablastyear <= 0 || $tablastyear > ($currentyear + 1)) {
            $tablastyear = $currentyear - 5;
        }
        for ($tab = $currentyear + 1; $tab >= $tablastyear; $tab--) {
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
        global $DB;

        $now = time();
        // Get all courses that the user is enrolled in.
        $usercourses = enrol_get_users_courses($userid, true, null, 'startdate DESC');
        $lastaccessbycourse = $DB->get_records_menu('user_lastaccess', ['userid' => $userid], '', 'courseid, timeaccess');
        $usercourse = [];

        foreach ($usercourses as $course) {
            $context = context_course::instance($course->id);
            $startyear = (int)userdate((int)$course->startdate, '%Y');
            $canviewhidden = has_capability('moodle/course:viewhiddencourses', $context, $userid);

            if ($startyear === (int)$year) {
                // Check if the course is visible or the user can view hidden courses.
                if (!empty($course->visible) || $canviewhidden) {
                    // Get the course category.
                    $categoryname = $this->block_mycourse_get_course_category_name($course->category);
                    $category = get_config('block_mycourse', 'coursecategory') ? $categoryname : null;
                    // Get course image.
                    $image = $this->block_mycourse_get_course_image($course->id);
                    // Get Course visibility.
                    $coursestatus = '';
                    if (empty($course->visible) && $canviewhidden) {
                        $coursestatus = get_string('coursestatus', 'block_mycourse');
                    }
                    // Get course start status.
                    $startstatus = null;
                    if (get_config('block_mycourse', 'coursestartstatus')) {
                        $formatteddate = userdate((int)$course->startdate, '%e %B');
                        if ((int)$course->startdate <= $now) {
                            $startstatus = get_string('coursestartedon', 'block_mycourse', $formatteddate);
                        } else {
                            $startstatus = get_string('coursestartson', 'block_mycourse', $formatteddate);
                        }
                    }
                    // Show completion to users who can view the course but cannot edit it.
                    $coursecompletion = null;
                    if (
                        has_capability('moodle/course:view', $context, $userid)
                        && !has_capability('moodle/course:update', $context, $userid)
                    ) {
                        $coursecompletion = $this->get_course_completion($course, $userid);
                    }
                    $hasprogress = ($coursecompletion !== null);
                    $progressvalue = null;
                    if ($hasprogress) {
                        // Keep displayed progress stable and bounded from 0 to 100.
                        $progressvalue = (int)round(max(0, min(100, (float)$coursecompletion)));
                    }
                    $lastaccess = isset($lastaccessbycourse[$course->id]) ? (int)$lastaccessbycourse[$course->id] : 0;
                    $lastaccesstext = null;
                    if ($lastaccess > 0 && $lastaccess <= $now) {
                        $lastaccesstext = get_string('lastaccessed', 'block_mycourse', format_time($now - $lastaccess));
                    }

                    $usercourse[] = [
                        'course' => $course,
                        'courseurl' => (new moodle_url('/course/view.php', ['id' => $course->id]))->out(false),
                        'category' => $category,
                        'categorylabel' => $categoryname,
                        'image' => $image,
                        'status' => $coursestatus,
                        'hasstartstatus' => !empty($startstatus),
                        'startstatus' => $startstatus,
                        'hasprogress' => $hasprogress,
                        'progress' => $progressvalue,
                        'haslastaccess' => !empty($lastaccesstext),
                        'lastaccesstext' => $lastaccesstext,
                        'categorysort' => core_text::strtolower(trim((string)$categoryname)),
                        'startdate' => (int)$course->startdate,
                        'lastaccess' => $lastaccess,
                        'hasstarted' => ((int)$course->startdate <= $now) ? 1 : 0,
                        'coursename' => core_text::strtolower(trim((string)$course->fullname)),
                        'coursefullname' => trim((string)$course->fullname),
                    ];
                }
            }
        }
        return $usercourse;
    }

    /**
     * Gets the course category name by category id.
     *
     * @param int $categoryid The category id.
     * @return string
     */
    public function block_mycourse_get_course_category_name($categoryid) {
        global $DB;
        static $categorycache = [];
        if (isset($categorycache[$categoryid])) {
            return $categorycache[$categoryid];
        }
        $categorycache[$categoryid] = (string)$DB->get_field(
            'course_categories',
            'name',
            ['id' => $categoryid],
            IGNORE_MISSING
        ) ?: '';
        return $categorycache[$categoryid];
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
            // Generate a deterministic and simple fallback image based on course identity.
            $seed = $course->id . ':' . (string)$course->shortname;
            $label = trim((string)$course->fullname);
            $label = $label !== '' ? core_text::strtoupper(core_text::substr($label, 0, 1)) : 'C';
            $courseimage = $this->generaterandomsvg($seed, $label);
        }

        return $courseimage;
    }

    /**
     * Generates a simple deterministic SVG fallback image.
     *
     * This method is called when no course overview image exists.
     *
     * @param string $seed Seed used to keep colors consistent per course.
     * @param string $label Single-character label to display.
     * @return string The SVG image data URI.
     */
    public function generaterandomsvg($seed = '', $label = 'C') {
        $bgcolor = $this->generatefallbackcolor($seed, 34, 90);
        $textcolor = $this->generatefallbackcolor($seed . ':text', 28, 36);
        $bordercolor = $this->generatefallbackcolor($seed . ':border', 26, 78);
        $char = core_text::strtoupper((string)$label);
        $char = preg_replace('/[^A-Z0-9]/', '', $char);
        if ($char === '') {
            $char = 'C';
        }
        $char = core_text::substr($char, 0, 1);
        $char = htmlspecialchars($char, ENT_QUOTES);

        $svg = '<svg xmlns="http://www.w3.org/2000/svg" width="320" height="320" viewBox="0 0 320 320">
            <rect x="0" y="0" width="320" height="320" rx="12" ry="12"
                fill="' . $bgcolor . '" stroke="' . $bordercolor . '" stroke-width="4"/>
            <text x="50%" y="56%" text-anchor="middle" dominant-baseline="middle"
                font-family="Arial, sans-serif" font-size="130" font-weight="600" fill="' . $textcolor . '">' . $char . '</text>
        </svg>';
        return 'data:image/svg+xml;base64,' . base64_encode($svg);
    }

    /**
     * Generates a deterministic fallback color.
     *
     * @param string $seed Input seed.
     * @param int $saturation Saturation percentage.
     * @param int $lightness Lightness percentage.
     * @return string HSL color string.
     */
    public function generatefallbackcolor($seed = '', $saturation = 30, $lightness = 85) {
        $hash = md5((string)$seed);
        $hue = hexdec(substr($hash, 0, 2)) % 360;
        return 'hsl(' . $hue . ', ' . (int)$saturation . '%, ' . (int)$lightness . '%)';
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
            return null;
        }

        // Get the course progress percentage.
        $progress = \core_completion\progress::get_course_progress_percentage($course, $userid);

        return $progress;
    }
}
