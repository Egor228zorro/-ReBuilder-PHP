<?php

declare(strict_types=1);

namespace Rebuilder\Training\Service;

use PDO;
use Rebuilder\Training\Database\DatabaseConnection;

class WorkoutService
{
    private PDO $db;

    public function __construct()
    {
        $this->db = DatabaseConnection::getInstance()->getConnection();
    }

    /**
     * @param array{name?: string, type?: string} $data
     * @return array<string, mixed>
     */
    public function createWorkout(array $data): array
    {
        $userId = '550e8400-e29b-41d4-a716-446655440000';
        $name = $data['name'] ?? 'Новая тренировка';
        $type = $data['type'] ?? 'strength';

        // Валидация
        if (empty($name)) {
            throw new \InvalidArgumentException('Workout name cannot be empty');
        }

        if (!in_array($type, ['strength', 'cardio', 'flexibility'])) {
            throw new \InvalidArgumentException('Invalid workout type');
        }

        $stmt = $this->db->prepare("
            INSERT INTO workouts (user_id, name, type) 
            VALUES (:user_id, :name, :type) 
            RETURNING *
        ");

        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare statement');
        }

        $result = $stmt->execute([
            'user_id' => $userId,
            'name' => $name,
            'type' => $type
        ]);

        if ($result === false) {
            throw new \RuntimeException('Failed to execute query');
        }

        $workout = $stmt->fetch(PDO::FETCH_ASSOC);

        if ($workout === false) {
            throw new \RuntimeException('Failed to create workout');
        }

        /** @var array<string, mixed> $workout */
        return $workout;
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function getWorkouts(): array
    {
        $stmt = $this->db->query("SELECT * FROM workouts ORDER BY created_at DESC");
        if ($stmt === false) {
            throw new \RuntimeException('Database query failed');
        }

        /** @var array<int, array<string, mixed>>|false $workouts */
        $workouts = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        if ($workouts === false) {
            throw new \RuntimeException('Failed to fetch workouts');
        }

        return $workouts;
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getWorkoutById(string $id): ?array
    {
        $stmt = $this->db->prepare("SELECT * FROM workouts WHERE id = :id");
        if ($stmt === false) {
            throw new \RuntimeException('Failed to prepare statement');
        }

        $result = $stmt->execute(['id' => $id]);
        if ($result === false) {
            throw new \RuntimeException('Failed to execute query');
        }

        /** @var array<string, mixed>|false $workout */
        $workout = $stmt->fetch(PDO::FETCH_ASSOC);
        
        return $workout !== false ? $workout : null;
    }
}
