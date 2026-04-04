<?php

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;

test('abandoning translation review writes nothing to database', function () {
    $sg = makeSubjectGrade();
    [$family, $version] = makeFamilyWithVersion($sg);
    $versionCountBefore = LessonPlanVersion::count();
    $familyCountBefore = LessonPlanFamily::count();

    // Simulate not calling translate() — no DB writes happen
    expect(LessonPlanVersion::count())->toBe($versionCountBefore);
    expect(LessonPlanFamily::count())->toBe($familyCountBefore);
});
