<?php

namespace App\Models;

use CodeIgniter\Model;

class RubricModel extends Model
{
    protected $table            = 'rubrics';
    protected $primaryKey       = 'id';
    protected $useAutoIncrement = true;
    protected $returnType       = 'array';
    protected $useSoftDeletes   = false;
    protected $protectFields    = true;
    protected $allowedFields    = [
        'teacher_id',
        'title',
        'description',
        'locked_at',
        'copied_from_id',
    ];

    protected array $casts = [
        'id'             => 'int',
        'teacher_id'     => 'int',
        'copied_from_id' => '?int',
    ];

    // Dates
    protected $useTimestamps = true;
    protected $dateFormat    = 'datetime';
    protected $createdField  = 'created_at';
    protected $updatedField  = 'updated_at';

    public function findForTeacher(int $id, int $teacherId): ?array
    {
        return $this->where('id', $id)->where('teacher_id', $teacherId)->first();
    }

    /**
     * The teacher's rubrics with criterion count, total points, and usage count.
     */
    public function forTeacher(int $teacherId): array
    {
        $rubrics  = $this->db->prefixTable('rubrics');
        $criteria = $this->db->prefixTable('rubric_criteria');
        $assigns  = $this->db->prefixTable('assignments');

        return $this->select('rubrics.*')
            ->select("(SELECT COUNT(*) FROM {$criteria} c WHERE c.rubric_id = {$rubrics}.id) AS criterion_count", false)
            ->select("(SELECT COALESCE(SUM(c.max_points), 0) FROM {$criteria} c WHERE c.rubric_id = {$rubrics}.id) AS total_points", false)
            ->select("(SELECT COUNT(*) FROM {$assigns} a WHERE a.rubric_id = {$rubrics}.id) AS assignment_count", false)
            ->where('teacher_id', $teacherId)
            ->orderBy('title')
            ->findAll();
    }

    /**
     * Criteria in order, each with its 'levels' in order.
     *
     * @return list<array<string, mixed>>
     */
    public function criteriaWithLevels(int $rubricId): array
    {
        $criteria = $this->db->table('rubric_criteria')
            ->where('rubric_id', $rubricId)
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->getResultArray();

        if ($criteria === []) {
            return [];
        }

        $levels = $this->db->table('rubric_levels')
            ->whereIn('criterion_id', array_column($criteria, 'id'))
            ->orderBy('sort_order')
            ->orderBy('id')
            ->get()
            ->getResultArray();

        $levelsByCriterion = [];

        foreach ($levels as $level) {
            $level['points'] = (int) $level['points'];
            $levelsByCriterion[$level['criterion_id']][] = $level;
        }

        foreach ($criteria as &$criterion) {
            $criterion['max_points'] = (int) $criterion['max_points'];
            $criterion['levels']     = $levelsByCriterion[$criterion['id']] ?? [];
        }

        return $criteria;
    }

    public static function totalPoints(array $criteria): int
    {
        return array_sum(array_column($criteria, 'max_points'));
    }

    public function isUsedByAssignments(int $rubricId): bool
    {
        return $this->db->table('assignments')->where('rubric_id', $rubricId)->countAllResults() > 0;
    }

    /**
     * Creates or updates a rubric and replaces all of its criteria and levels.
     *
     * Only call this for unlocked rubrics: replacing criteria gives them new IDs,
     * which is safe only while no evaluation refers to them.
     *
     * @param array{title: string, description: string|null} $rubric
     * @param list<array<string, mixed>>                     $criteria As returned by RubricForm::parse().
     *
     * @return int The rubric ID.
     */
    public function saveWithCriteria(?int $rubricId, int $teacherId, array $rubric, array $criteria): int
    {
        $this->db->transException(true)->transStart();

        if ($rubricId === null) {
            $rubricId = $this->insert($rubric + ['teacher_id' => $teacherId]);
        } else {
            $this->update($rubricId, $rubric);
            // Levels are removed by the ON DELETE CASCADE foreign key.
            $this->db->table('rubric_criteria')->where('rubric_id', $rubricId)->delete();
        }

        $this->insertCriteria($rubricId, $criteria);

        $this->db->transComplete();

        return $rubricId;
    }

    /**
     * Copies a rubric with all criteria and levels into a new, unlocked rubric.
     */
    public function duplicate(array $source, int $teacherId): int
    {
        $criteria = $this->criteriaWithLevels($source['id']);

        $this->db->transException(true)->transStart();

        $newId = $this->insert([
            'teacher_id'     => $teacherId,
            'title'          => mb_substr('Copy of ' . $source['title'], 0, 150),
            'description'    => $source['description'],
            'copied_from_id' => $source['id'],
        ]);

        $this->insertCriteria($newId, $criteria);

        $this->db->transComplete();

        return $newId;
    }

    private function insertCriteria(int $rubricId, array $criteria): void
    {
        foreach (array_values($criteria) as $order => $criterion) {
            $this->db->table('rubric_criteria')->insert([
                'rubric_id'   => $rubricId,
                'title'       => $criterion['title'],
                'description' => $criterion['description'],
                'max_points'  => $criterion['max_points'],
                'sort_order'  => $order,
            ]);
            $criterionId = (int) $this->db->insertID();

            foreach (array_values($criterion['levels']) as $levelOrder => $level) {
                $this->db->table('rubric_levels')->insert([
                    'criterion_id' => $criterionId,
                    'label'        => $level['label'],
                    'description'  => $level['description'],
                    'points'       => $level['points'],
                    'sort_order'   => $levelOrder,
                ]);
            }
        }
    }
}
