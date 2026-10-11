<?php
declare(strict_types=1);

namespace TalentEngine;

use PDO;

/** Accès SQLite. Seul endroit du projet qui écrit du SQL (toujours en requêtes préparées). */
final class Repository
{
    public const REVIEW_STATUSES = [
        'nouveau'  => 'Nouveau',
        'en_revue' => 'En revue',
        'entretien'=> 'Entretien prévu',
        'retenu'   => 'Retenu',
        'refuse'   => 'Refusé',
    ];

    private const JSON_COLUMNS = ['skills', 'breakdown', 'flags', 'disqualifications'];

    public function __construct(private readonly PDO $pdo)
    {
        $this->pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
        $this->pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
        $this->migrate();
    }

    public static function open(string $path): self
    {
        if ($path !== ':memory:' && !is_dir(dirname($path))) {
            mkdir(dirname($path), 0775, true);
        }
        return new self(new PDO('sqlite:' . $path));
    }

    private function migrate(): void
    {
        $this->pdo->exec(<<<SQL
            CREATE TABLE IF NOT EXISTS candidates (
                id                  INTEGER PRIMARY KEY AUTOINCREMENT,
                created_at          TEXT NOT NULL,
                updated_at          TEXT NOT NULL,
                program             TEXT NOT NULL,
                first_name          TEXT NOT NULL,
                last_name           TEXT NOT NULL,
                email               TEXT NOT NULL,
                phone               TEXT NOT NULL DEFAULT '',
                country             TEXT NOT NULL,
                city                TEXT NOT NULL DEFAULT '',
                education           TEXT NOT NULL,
                experience_years    REAL NOT NULL,
                skills              TEXT NOT NULL,
                other_skills        TEXT NOT NULL DEFAULT '',
                availability        TEXT NOT NULL,
                available_from      TEXT NOT NULL DEFAULT '',
                french_level        TEXT NOT NULL,
                english_level       TEXT NOT NULL,
                portfolio_url       TEXT NOT NULL DEFAULT '',
                project_description TEXT NOT NULL DEFAULT '',
                motivation          TEXT NOT NULL,
                score               INTEGER NOT NULL,
                tier                TEXT NOT NULL,
                qualified           INTEGER NOT NULL,
                breakdown           TEXT NOT NULL,
                flags               TEXT NOT NULL,
                disqualifications   TEXT NOT NULL,
                scoring_version     TEXT NOT NULL,
                review_status       TEXT NOT NULL DEFAULT 'nouveau',
                reviewer_note       TEXT NOT NULL DEFAULT '',
                UNIQUE (email, program)
            );
            CREATE INDEX IF NOT EXISTS idx_candidates_program_score ON candidates (program, score DESC);
        SQL);
    }

    public function emailExists(string $email, string $program): bool
    {
        $stmt = $this->pdo->prepare('SELECT 1 FROM candidates WHERE email = ? AND program = ?');
        $stmt->execute([$email, $program]);
        return (bool) $stmt->fetchColumn();
    }

    public function phoneExists(string $phone, string $program, ?int $excludeId = null): bool
    {
        if ($phone === '') {
            return false;
        }
        $stmt = $this->pdo->prepare('SELECT 1 FROM candidates WHERE phone = ? AND program = ? AND id != ?');
        $stmt->execute([$phone, $program, $excludeId ?? 0]);
        return (bool) $stmt->fetchColumn();
    }

    /** @throws DuplicateCandidate si l'e-mail est déjà inscrit à ce programme */
    public function insert(array $data, array $result, ?string $createdAt = null): int
    {
        $now = $createdAt ?? gmdate('Y-m-d H:i:s');
        $row = $data + $this->resultColumns($result) + ['created_at' => $now, 'updated_at' => $now];
        $row['skills'] = json_encode($row['skills'], JSON_UNESCAPED_UNICODE);

        $columns = array_keys($row);
        $sql = sprintf(
            'INSERT INTO candidates (%s) VALUES (%s)',
            implode(', ', $columns),
            implode(', ', array_map(static fn($c) => ":$c", $columns))
        );
        try {
            $this->pdo->prepare($sql)->execute($row);
        } catch (\PDOException $e) {
            if (str_contains($e->getMessage(), 'UNIQUE')) {
                throw new DuplicateCandidate('Une candidature existe déjà avec cet e-mail pour ce programme.', 0, $e);
            }
            throw $e;
        }
        return (int) $this->pdo->lastInsertId();
    }

    public function updateScore(int $id, array $result): void
    {
        $cols = $this->resultColumns($result) + ['updated_at' => gmdate('Y-m-d H:i:s'), 'id' => $id];
        $set = implode(', ', array_map(static fn($c) => "$c = :$c", array_diff(array_keys($cols), ['id'])));
        $this->pdo->prepare("UPDATE candidates SET $set WHERE id = :id")->execute($cols);
    }

    public function updateReview(int $id, string $status, string $note): void
    {
        if (!isset(self::REVIEW_STATUSES[$status])) {
            throw new \InvalidArgumentException("Statut inconnu : $status");
        }
        $this->pdo->prepare('UPDATE candidates SET review_status = ?, reviewer_note = ?, updated_at = ? WHERE id = ?')
            ->execute([$status, mb_substr($note, 0, 2000), gmdate('Y-m-d H:i:s'), $id]);
    }

    public function find(int $id): ?array
    {
        $stmt = $this->pdo->prepare('SELECT * FROM candidates WHERE id = ?');
        $stmt->execute([$id]);
        $row = $stmt->fetch();
        return $row ? $this->hydrate($row) : null;
    }

    /** @return list<array> */
    public function all(?string $program = null): array
    {
        $stmt = $program === null
            ? $this->pdo->query('SELECT * FROM candidates')
            : $this->pdo->prepare('SELECT * FROM candidates WHERE program = ?');
        if ($program !== null) {
            $stmt->execute([$program]);
        }
        return array_map($this->hydrate(...), $stmt->fetchAll());
    }

    public function deleteAll(): void
    {
        $this->pdo->exec('DELETE FROM candidates');
    }

    private function resultColumns(array $result): array
    {
        return [
            'score'             => $result['score'],
            'tier'              => $result['tier'],
            'qualified'         => $result['qualified'] ? 1 : 0,
            'breakdown'         => json_encode($result['breakdown'], JSON_UNESCAPED_UNICODE),
            'flags'             => json_encode($result['flags'], JSON_UNESCAPED_UNICODE),
            'disqualifications' => json_encode($result['disqualifications'], JSON_UNESCAPED_UNICODE),
            'scoring_version'   => $result['version'],
        ];
    }

    private function hydrate(array $row): array
    {
        foreach (self::JSON_COLUMNS as $col) {
            $row[$col] = json_decode($row[$col], true) ?? [];
        }
        $row['id'] = (int) $row['id'];
        $row['score'] = (int) $row['score'];
        $row['qualified'] = (bool) $row['qualified'];
        $row['experience_years'] = (float) $row['experience_years'];
        return $row;
    }
}
