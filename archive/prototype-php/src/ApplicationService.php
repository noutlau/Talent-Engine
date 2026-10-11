<?php
declare(strict_types=1);

namespace TalentEngine;

/**
 * Le pipeline complet : recevoir → valider → dédoublonner → qualifier/scorer → stocker.
 * Utilisé à l'identique par le formulaire web et par l'import CSV.
 */
final class ApplicationService
{
    public function __construct(
        private readonly Validator $validator,
        private readonly Scorer $scorer,
        private readonly Repository $repository,
    ) {
    }

    /** @return array{ok: bool, errors?: array<string, string>, id?: int, result?: array} */
    public function submit(array $input, ?string $createdAt = null): array
    {
        [$data, $errors] = $this->validator->validate($input);
        if ($errors !== []) {
            return ['ok' => false, 'errors' => $errors];
        }

        $duplicateError = ['ok' => false, 'errors' => ['email' => 'Une candidature existe déjà avec cet e-mail pour ce programme.']];
        if ($this->repository->emailExists($data['email'], $data['program'])) {
            return $duplicateError;
        }

        $result = $this->withDuplicatePhoneFlag($this->scorer->score($data), $data);

        try {
            $id = $this->repository->insert($data, $result, $createdAt);
        } catch (DuplicateCandidate) {
            // Deux envois simultanés : la contrainte UNIQUE de la base a tranché.
            return $duplicateError;
        }
        return ['ok' => true, 'id' => $id, 'result' => $result];
    }

    /** Recalcule tous les scores après un changement de règles. Retourne le nombre de candidats rescorés. */
    public function rescoreAll(): int
    {
        $candidates = $this->repository->all();
        foreach ($candidates as $c) {
            $result = $this->withDuplicatePhoneFlag($this->scorer->score($c), $c, $c['id']);
            $this->repository->updateScore($c['id'], $result);
        }
        return count($candidates);
    }

    private function withDuplicatePhoneFlag(array $result, array $data, ?int $excludeId = null): array
    {
        if ($this->repository->phoneExists($data['phone'], $data['program'], $excludeId)) {
            $result['flags'][] = 'Doublon possible : ce numéro de téléphone figure dans une autre candidature.';
        }
        return $result;
    }
}
