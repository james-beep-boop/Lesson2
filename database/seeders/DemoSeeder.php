<?php

namespace Database\Seeders;

use App\Models\LessonPlanFamily;
use App\Models\LessonPlanVersion;
use App\Models\Subject;
use App\Models\SubjectGrade;
use App\Models\User;
use App\Services\FavoriteService;
use Illuminate\Database\Seeder;
use Spatie\Permission\Models\Role;

class DemoSeeder extends Seeder
{
    public function run(): void
    {
        // Ensure roles exist.
        Role::firstOrCreate(['name' => 'site_administrator', 'guard_name' => 'web']);

        // --- Subjects ---
        $maths   = Subject::firstOrCreate(['name' => 'Mathematics']);
        $english = Subject::firstOrCreate(['name' => 'English']);
        $science = Subject::firstOrCreate(['name' => 'Science']);
        Subject::firstOrCreate(['name' => 'Kiswahili']);

        // --- Subject Grades ---
        $mathG10  = SubjectGrade::firstOrCreate(['subject_id' => $maths->id, 'grade' => 10]);
        $mathG11  = SubjectGrade::firstOrCreate(['subject_id' => $maths->id, 'grade' => 11]);
        $englG10  = SubjectGrade::firstOrCreate(['subject_id' => $english->id, 'grade' => 10]);
        $sciG10   = SubjectGrade::firstOrCreate(['subject_id' => $science->id, 'grade' => 10]);

        // --- Demo Users ---
        $alice = $this->makeUser('Alice Kamau', 'alice', 'alice@demo.test');
        $bob   = $this->makeUser('Bob Ochieng', 'bob', 'bob@demo.test');
        $carol = $this->makeUser('Carol Mwangi', 'carol', 'carol@demo.test');
        $david = $this->makeUser('David Njoroge', 'david', 'david@demo.test');
        $eve   = $this->makeUser('Eve Wanjiku', 'eve', 'eve@demo.test');

        // Alice — Subject Admin for Mathematics Grade 10
        $mathG10->update(['subject_admin_user_id' => $alice->id]);

        // Bob — Editor for Mathematics Grade 10
        \DB::table('subject_grade_user')->updateOrInsert(
            ['subject_grade_id' => $mathG10->id, 'user_id' => $bob->id],
            ['role' => 'editor', 'created_at' => now(), 'updated_at' => now()]
        );

        // Carol — Editor for Science Grade 10
        \DB::table('subject_grade_user')->updateOrInsert(
            ['subject_grade_id' => $sciG10->id, 'user_id' => $carol->id],
            ['role' => 'editor', 'created_at' => now(), 'updated_at' => now()]
        );

        // David — Teacher only (no assignment)

        // Eve — Subject Admin for Science Grade 10
        $sciG10->update(['subject_admin_user_id' => $eve->id]);

        // --- Demo lesson plan: Maths Grade 10, Day 1 ---
        $family = LessonPlanFamily::firstOrCreate(
            ['subject_grade_id' => $mathG10->id, 'day' => '1'],
            ['official_version_id' => null]
        );

        $v100 = LessonPlanVersion::firstOrCreate(
            ['lesson_plan_family_id' => $family->id, 'version' => '1.0.0'],
            [
                'contributor_id' => $alice->id,
                'content' => "# Mathematics Grade 10 — Day 1\n\n## Learning Objectives\n\n- Understand quadratic equations\n- Solve using factoring\n\n## Activities\n\n1. Introduction (10 min)\n2. Worked examples (20 min)\n3. Practice problems (15 min)\n\n## Assessment\n\nExit ticket: solve two quadratic equations.",
                'revision_note' => 'Initial version',
            ]
        );

        $v110 = LessonPlanVersion::firstOrCreate(
            ['lesson_plan_family_id' => $family->id, 'version' => '1.1.0'],
            [
                'contributor_id' => $bob->id,
                'content' => "# Mathematics Grade 10 — Day 1\n\n## Learning Objectives\n\n- Understand quadratic equations\n- Solve using factoring and the quadratic formula\n\n## Activities\n\n1. Introduction (10 min)\n2. Worked examples (20 min)\n3. Partner practice (15 min)\n4. Group discussion (5 min)\n\n## Assessment\n\nExit ticket: solve two quadratic equations using your preferred method.",
                'revision_note' => 'Added quadratic formula and group discussion',
            ]
        );

        $v200 = LessonPlanVersion::firstOrCreate(
            ['lesson_plan_family_id' => $family->id, 'version' => '2.0.0'],
            [
                'contributor_id' => $alice->id,
                'content' => "# Mathematics Grade 10 — Day 1 (Revised)\n\n## Learning Objectives\n\n- Understand and apply quadratic equations\n- Connect to real-world applications\n\n## Activities\n\n1. Warm-up: real-world quadratic puzzle (5 min)\n2. Direct instruction (15 min)\n3. Worked examples with student input (15 min)\n4. Partner practice (15 min)\n\n## Assessment\n\nFormative: whiteboard responses. Exit ticket: one real-world quadratic problem.",
                'revision_note' => 'Major revision — real-world integration',
            ]
        );

        // Set v1.1.0 as official version.
        $family->update(['official_version_id' => $v110->id]);

        // Alice favorites v1.0.0 (different from official v1.1.0 — exercises the UI state).
        $favoriteService = app(FavoriteService::class);
        $favoriteService->upsert($alice, $v100);

        // --- Demo lesson plan: Science Grade 10, Day 1 — no official version ---
        $sciFamily = LessonPlanFamily::firstOrCreate(
            ['subject_grade_id' => $sciG10->id, 'day' => '1'],
            ['official_version_id' => null]
        );

        LessonPlanVersion::firstOrCreate(
            ['lesson_plan_family_id' => $sciFamily->id, 'version' => '1.0.0'],
            [
                'contributor_id' => $eve->id,
                'content' => "# Science Grade 10 — Day 1\n\n## Topic\n\nCell biology: structure and function.\n\n## Activities\n\n1. Diagram labelling (15 min)\n2. Microscope observation (20 min)\n3. Written summary (10 min)\n",
                'revision_note' => null,
            ]
        );
        // No official version set — exercises the no-official-version fallback.
    }

    private function makeUser(string $name, string $username, string $email): User
    {
        return User::firstOrCreate(
            ['email' => $email],
            [
                'username' => $username,
                'name' => $name,
                'password' => 'password',
                'email_verified_at' => now(),
            ]
        );
    }
}
