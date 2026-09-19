<?php

final class AcademicEligibilityService
{
    public function __construct(private AcademicChecksheetService $checksheetService)
    {
    }

    /** @return array<string, mixed>|null */
    public function assess(int $studentId, int $programmeId = 0): ?array
    {
        $checksheet = $this->checksheetService->build($studentId, $programmeId);
        if ($checksheet === null) {
            return null;
        }

        $programme = $checksheet['programme'];
        $summary = $checksheet['summary'];
        $courses = $checksheet['courses'];
        $enrolmentValid = in_array((string) ($programme['enrolment_status'] ?? ''), ['active', 'completed'], true);
        $coursesConfigured = $courses !== [];
        $approvedRequiredResults = 0;
        $missingApprovedResults = [];

        foreach ($courses as $course) {
            if ((int) ($course['is_compulsory'] ?? 0) !== 1) {
                continue;
            }

            if (($course['result_status'] ?? null) === 'approved') {
                $approvedRequiredResults++;
            } else {
                $missingApprovedResults[] = $course;
            }
        }

        $requirements = [
            [
                'label' => 'Valid programme enrolment',
                'passed' => $enrolmentValid,
                'detail' => $enrolmentValid
                    ? 'Enrolment status: ' . (string) $programme['enrolment_status'] . '.'
                    : 'A student must have an active or completed programme enrolment.',
            ],
            [
                'label' => 'Programme course structure',
                'passed' => $coursesConfigured,
                'detail' => $coursesConfigured
                    ? count($courses) . ' programme course requirement(s) found.'
                    : 'No courses are assigned to this programme.',
            ],
            [
                'label' => 'Required courses completed',
                'passed' => $summary['outstanding_required_courses'] === 0 && $summary['required_courses'] > 0,
                'detail' => (int) $summary['completed_required_courses'] . ' of ' . (int) $summary['required_courses'] . ' required courses completed.',
            ],
            [
                'label' => 'Required credits completed',
                'passed' => (float) $summary['completed_credits'] >= (float) $summary['required_credits'] && (float) $summary['required_credits'] > 0,
                'detail' => number_format((float) $summary['completed_credits'], 2) . ' of ' . number_format((float) $summary['required_credits'], 2) . ' required credits completed.',
            ],
            [
                'label' => 'Required results approved',
                'passed' => $approvedRequiredResults === (int) $summary['required_courses'] && (int) $summary['required_courses'] > 0,
                'detail' => $approvedRequiredResults . ' of ' . (int) $summary['required_courses'] . ' required course results approved.',
            ],
        ];

        $eligible = !in_array(false, array_column($requirements, 'passed'), true);

        return [
            'checksheet' => $checksheet,
            'requirements' => $requirements,
            'eligible' => $eligible,
            'outstanding_courses' => $summary['outstanding_required'],
            'missing_approved_results' => $missingApprovedResults,
        ];
    }
}
