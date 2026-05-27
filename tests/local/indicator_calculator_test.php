<?php
namespace local_academicpanel\local;

defined('MOODLE_INTERNAL') || die();

class indicator_calculator_test extends \advanced_testcase {

    public function test_calculate_basic_metrics() {
        $students = [
            ['finalgrade' => 8.0, 'hasgrade' => true, 'accessed' => true, 'engaged' => true],
            ['finalgrade' => 5.5, 'hasgrade' => true, 'accessed' => true, 'engaged' => false],
            ['finalgrade' => null, 'hasgrade' => false, 'accessed' => false, 'engaged' => false],
        ];

        $metrics = indicator_calculator::calculate($students, 7.0);

        $this->assertSame(3, $metrics['enrolled']);
        $this->assertSame(2, $metrics['withgrade']);
        $this->assertSame(1, $metrics['approved']);
        $this->assertSame(1, $metrics['failed']);
        $this->assertSame(1, $metrics['engaged']);
        $this->assertSame(1, $metrics['neveraccessed']);
        $this->assertSame(1, $metrics['abandoned']);
        $this->assertEqualsWithDelta(50.0, $metrics['approvalamonggraded'], 0.0001);
        $this->assertEqualsWithDelta(33.33, $metrics['approvalamongenrolled'], 0.01);
        $this->assertEqualsWithDelta(50.0, $metrics['failureamonggraded'], 0.0001);
        $this->assertEqualsWithDelta(33.33, $metrics['failureamongenrolled'], 0.01);
        $this->assertEqualsWithDelta(33.33, $metrics['engagementrate'], 0.01);
        $this->assertEqualsWithDelta(33.33, $metrics['neveraccessedrate'], 0.01);
        $this->assertEqualsWithDelta(33.33, $metrics['abandonmentrate'], 0.01);
    }

    public function test_merge_aggregates_counts() {
        $base = [
            'enrolled' => 3,
            'withgrade' => 2,
            'approved' => 1,
            'failed' => 1,
            'engaged' => 1,
            'neveraccessed' => 1,
            'abandoned' => 1,
        ];

        $merged = indicator_calculator::merge([$base, $base]);

        $this->assertSame(6, $merged['enrolled']);
        $this->assertSame(4, $merged['withgrade']);
        $this->assertSame(2, $merged['approved']);
        $this->assertSame(2, $merged['failed']);
        $this->assertEqualsWithDelta(50.0, $merged['approvalamonggraded'], 0.0001);
    }

    public function test_empty_input_returns_zero_rates() {
        $metrics = indicator_calculator::calculate([], 7.0);

        $this->assertSame(0, $metrics['enrolled']);
        $this->assertSame(0.0, $metrics['approvalamonggraded']);
        $this->assertSame(0.0, $metrics['engagementrate']);
    }

    public function test_grade_cutoff_boundary() {
        $students = [
            ['finalgrade' => 7.0, 'hasgrade' => true, 'accessed' => true, 'engaged' => true],
            ['finalgrade' => 6.99, 'hasgrade' => true, 'accessed' => true, 'engaged' => true],
        ];

        $metrics = indicator_calculator::calculate($students, 7.0);

        $this->assertSame(1, $metrics['approved']);
        $this->assertSame(1, $metrics['failed']);
    }
}
