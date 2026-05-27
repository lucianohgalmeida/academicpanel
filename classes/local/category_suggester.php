<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

class category_suggester {

    public static function suggest_from_semester_category($semestercategoryid) {
        $semester = \core_course_category::get($semestercategoryid, MUST_EXIST, true);
        $suggestions = [];

        foreach ($semester->get_children() as $child) {
            $suggestions[] = (object)[
                'semester' => $semester->name,
                'programname' => $child->name,
                'programshortname' => self::shortname_from_name($child->name),
                'categoryid' => $child->id,
                'origin' => 'suggested',
            ];
        }

        return $suggestions;
    }

    private static function shortname_from_name($name) {
        $shortname = strtolower(remove_accents($name));
        $shortname = preg_replace('/[^a-z0-9]+/', '_', $shortname);
        $shortname = trim($shortname, '_');
        if ($shortname === '') {
            return 'program';
        }

        return $shortname;
    }
}
