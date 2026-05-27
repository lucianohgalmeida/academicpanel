<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

class dashboard_chart_data {

    public static function build(array $snapshots, array $courses) {
        $bars = [];
        $metricrows = [];

        foreach ($snapshots as $snapshot) {
            $metrics = self::metrics_from_snapshot($snapshot);
            $calculated = indicator_calculator::merge([$metrics]);
            $metricrows[] = $metrics;

            $bars[] = [
                'label' => self::course_label($snapshot, $courses),
                'approval' => $calculated['approvalamonggraded'],
                'failure' => $calculated['failureamonggraded'],
                'engagement' => $calculated['engagementrate'],
                'abandonment' => $calculated['abandonmentrate'],
            ];
        }

        $summary = indicator_calculator::merge($metricrows);

        return [
            'bars' => $bars,
            'participation' => [
                'engaged' => $summary['engaged'],
                'abandoned' => $summary['abandoned'],
                'neveraccessed' => $summary['neveraccessed'],
            ],
            'outcomes' => [
                'approved' => $summary['approved'],
                'failed' => $summary['failed'],
                'ungraded' => max(0, $summary['enrolled'] - $summary['withgrade']),
            ],
        ];
    }

    private static function metrics_from_snapshot($snapshot) {
        return [
            'enrolled' => (int)$snapshot->enrolled,
            'withgrade' => (int)$snapshot->withgrade,
            'approved' => (int)$snapshot->approved,
            'failed' => (int)$snapshot->failed,
            'engaged' => (int)$snapshot->engaged,
            'neveraccessed' => (int)$snapshot->neveraccessed,
            'abandoned' => (int)$snapshot->abandoned,
        ];
    }

    private static function course_label($snapshot, array $courses) {
        if (isset($courses[$snapshot->courseid]) && !empty($courses[$snapshot->courseid]->fullname)) {
            return $courses[$snapshot->courseid]->fullname;
        }

        return (string)$snapshot->courseid;
    }
}
