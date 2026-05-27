<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

class indicator_calculator {

    public static function calculate(array $students, $cutoff) {
        $metrics = self::empty_metrics();
        $metrics['enrolled'] = count($students);

        foreach ($students as $student) {
            $accessed = !empty($student['accessed']);
            $engaged = !empty($student['engaged']);
            $hasgrade = !empty($student['hasgrade']) && $student['finalgrade'] !== null;

            if ($hasgrade) {
                $metrics['withgrade']++;
                if ((float)$student['finalgrade'] >= (float)$cutoff) {
                    $metrics['approved']++;
                } else {
                    $metrics['failed']++;
                }
            }

            if ($engaged) {
                $metrics['engaged']++;
            } else if (!$accessed) {
                $metrics['neveraccessed']++;
            } else {
                $metrics['abandoned']++;
            }
        }

        return self::with_rates($metrics);
    }

    public static function merge(array $metricsets) {
        $merged = self::empty_metrics();
        $countfields = ['enrolled', 'withgrade', 'approved', 'failed', 'engaged', 'neveraccessed', 'abandoned'];

        foreach ($metricsets as $metrics) {
            foreach ($countfields as $field) {
                if (isset($metrics[$field])) {
                    $merged[$field] += (int)$metrics[$field];
                }
            }
        }

        return self::with_rates($merged);
    }

    private static function empty_metrics() {
        return [
            'enrolled' => 0,
            'withgrade' => 0,
            'approved' => 0,
            'failed' => 0,
            'engaged' => 0,
            'neveraccessed' => 0,
            'abandoned' => 0,
            'approvalamonggraded' => 0.0,
            'approvalamongenrolled' => 0.0,
            'failureamonggraded' => 0.0,
            'failureamongenrolled' => 0.0,
            'engagementrate' => 0.0,
            'neveraccessedrate' => 0.0,
            'abandonmentrate' => 0.0,
        ];
    }

    private static function with_rates(array $metrics) {
        $metrics['approvalamonggraded'] = self::rate($metrics['approved'], $metrics['withgrade']);
        $metrics['approvalamongenrolled'] = self::rate($metrics['approved'], $metrics['enrolled']);
        $metrics['failureamonggraded'] = self::rate($metrics['failed'], $metrics['withgrade']);
        $metrics['failureamongenrolled'] = self::rate($metrics['failed'], $metrics['enrolled']);
        $metrics['engagementrate'] = self::rate($metrics['engaged'], $metrics['enrolled']);
        $metrics['neveraccessedrate'] = self::rate($metrics['neveraccessed'], $metrics['enrolled']);
        $metrics['abandonmentrate'] = self::rate($metrics['abandoned'], $metrics['enrolled']);

        return $metrics;
    }

    private static function rate($value, $total) {
        if ((int)$total === 0) {
            return 0.0;
        }

        return round(((float)$value / (float)$total) * 100, 2);
    }
}
