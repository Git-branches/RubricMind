<?php

namespace App\Database\Seeds;

use App\Models\AssignmentModel;
use App\Models\ClassModel;
use App\Models\RubricModel;
use App\Models\SubmissionModel;
use App\Models\UserModel;
use CodeIgniter\CLI\CLI;
use CodeIgniter\Database\Seeder;

/**
 * Optional sample data for demos and screenshots: one teacher, six students,
 * two classes, a rubric, three assignments, and a few essays.
 *
 * Run: php spark db:seed DemoSeeder
 * Every demo account uses the password below and does not need to change it.
 */
class DemoSeeder extends Seeder
{
    private const PASSWORD      = 'Demo1234!';
    private const TEACHER_EMAIL = 'maria.santos@rubricmind.local';

    private const STUDENTS = [
        'Juan Dela Cruz',
        'Angela Reyes',
        'Mark Villanueva',
        'Sofia Bautista',
        'Paolo Garcia',
        'Bea Mendoza',
    ];

    private const ESSAYS = [
        "Many students arrive at school tired because the first class starts too early. Research on teenagers shows that their sleep cycle shifts later during adolescence, so waking at five in the morning works against their bodies.\n\nIf classes began at nine instead of seven, students would sleep longer, pay closer attention, and perform better on tests. Schools that have tried later start times report fewer absences and better grades.\n\nTherefore, our school should move the start of classes to a later time.",
        "School should not start later. Parents need to go to work early and many of them drop their children off on the way. A later start would make this difficult for families.\n\nAlso, if school starts later it will also end later, which leaves less time for sports, part-time work, and homework. Students can simply sleep earlier at night instead.",
        "I believe a later start time would help students, however it must be planned carefully. Sleep is important for memory and mood, and tired students cannot learn well.\n\nOn the other hand, transportation schedules and after-school activities would need to change. A compromise, such as starting thirty minutes later, could give students more rest without disrupting families too much.",
        "Starting school later is a good idea because students is always sleepy in the morning. When I am sleepy I cannot understand the lesson. My classmates also feel the same and some of them falls asleep in class.\n\nIn conclusion school should start later so that everyone can learn better.",
    ];

    public function run()
    {
        $users = model(UserModel::class);

        if ($users->findByEmail(self::TEACHER_EMAIL) !== null) {
            CLI::write('Demo data already exists. Nothing changed.', 'yellow');

            return;
        }

        $hash = UserModel::hashPassword(self::PASSWORD);

        $teacherId = $users->insert([
            'name'          => 'Maria Santos',
            'email'         => self::TEACHER_EMAIL,
            'password_hash' => $hash,
            'role'          => 'teacher',
            'status'        => 'active',
        ]);

        $studentIds = [];

        foreach (self::STUDENTS as $name) {
            $email = strtolower(str_replace(' ', '.', $name)) . '@rubricmind.local';

            $studentIds[] = $users->insert([
                'name'          => $name,
                'email'         => $email,
                'password_hash' => $hash,
                'role'          => 'student',
                'status'        => 'active',
            ]);
        }

        $classes = model(ClassModel::class);

        $englishId = $classes->insert([
            'teacher_id'  => $teacherId,
            'name'        => 'English 10',
            'section'     => 'Grade 10 – Rizal',
            'school_year' => '2026–2027',
            'description' => 'Reading and writing: argumentative and persuasive essays.',
            'status'      => 'active',
        ]);

        $creativeId = $classes->insert([
            'teacher_id'  => $teacherId,
            'name'        => 'Creative Writing',
            'section'     => 'Grade 10 – Bonifacio',
            'school_year' => '2026–2027',
            'status'      => 'active',
        ]);

        $emails = array_map(static fn (string $name) => strtolower(str_replace(' ', '.', $name)) . '@rubricmind.local', self::STUDENTS);
        $classes->enrollByEmail($englishId, $emails);
        $classes->enrollByEmail($creativeId, array_slice($emails, 0, 3));

        $rubricId = model(RubricModel::class)->saveWithCriteria(null, $teacherId, [
            'title'       => 'Argumentative Essay Rubric',
            'description' => 'Used for all argumentative essays in English 10.',
        ], [
            $this->criterion('Thesis statement', 'States a clear, arguable position.', 10),
            $this->criterion('Evidence and support', 'Uses relevant facts, examples, or research to support each claim.', 10),
            $this->criterion('Organization', 'Ideas follow a logical order with clear transitions.', 10),
            $this->criterion('Grammar and mechanics', 'Correct grammar, spelling, and punctuation.', 5),
        ]);

        $assignments = model(AssignmentModel::class);

        $openId = $assignments->insert([
            'class_id'     => $englishId,
            'rubric_id'    => $rubricId,
            'title'        => 'Should School Start Later?',
            'instructions' => "Write a 300–500 word argumentative essay answering the question: Should our school start classes later in the morning?\n\nState your position clearly, support it with at least two pieces of evidence, and address one counterargument.",
            'due_at'       => date('Y-m-d 23:59:00', strtotime('+5 days')),
            'status'       => 'published',
        ]);

        $assignments->insert([
            'class_id'     => $englishId,
            'rubric_id'    => $rubricId,
            'title'        => 'Social Media and Teenagers',
            'instructions' => 'Argue whether social media does more harm than good for teenagers.',
            'due_at'       => date('Y-m-d 23:59:00', strtotime('+14 days')),
            'status'       => 'draft',
        ]);

        $assignments->insert([
            'class_id'     => $creativeId,
            'rubric_id'    => $rubricId,
            'title'        => 'A Place That Matters',
            'instructions' => 'Describe a place that is important to you and explain why.',
            'due_at'       => date('Y-m-d 17:00:00', strtotime('-3 days')),
            'status'       => 'closed',
        ]);

        $submissions = model(SubmissionModel::class);

        foreach (self::ESSAYS as $i => $essay) {
            $submissions->saveEssay(null, $openId, $studentIds[$i], $essay, false);
        }

        CLI::write('Demo data created.', 'green');
        CLI::write('  Teacher:  ' . self::TEACHER_EMAIL);
        CLI::write('  Students: ' . implode(', ', $emails));
        CLI::write('  Password for all demo accounts: ' . self::PASSWORD);
    }

    private function criterion(string $title, string $description, int $max): array
    {
        return [
            'title'       => $title,
            'description' => $description,
            'max_points'  => $max,
            'levels'      => [
                ['label' => 'Excellent', 'description' => null, 'points' => $max],
                ['label' => 'Proficient', 'description' => null, 'points' => (int) round($max * 0.8)],
                ['label' => 'Developing', 'description' => null, 'points' => (int) round($max * 0.6)],
                ['label' => 'Beginning', 'description' => null, 'points' => (int) round($max * 0.4)],
            ],
        ];
    }
}
