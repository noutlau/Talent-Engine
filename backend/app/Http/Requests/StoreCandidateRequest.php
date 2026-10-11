<?php

namespace App\Http\Requests;

use App\Enums\Availability;
use App\Enums\EducationField;
use App\Enums\EducationLevel;
use App\Enums\ExperienceType;
use App\Enums\SkillLevel;
use Closure;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

class StoreCandidateRequest extends FormRequest
{
    public const DUPLICATE_EMAIL = 'Une candidature associée à cette adresse email existe déjà.';

    public function authorize(): bool
    {
        return true; // formulaire public
    }

    /** Normalise avant de valider : « Jean.P@Mail.COM », « +237 690 11-22-33 », « 2,5 ». */
    protected function prepareForValidation(): void
    {
        $this->merge(array_filter([
            'email' => is_string($this->email) ? mb_strtolower(trim($this->email)) : null,
            'phone' => is_string($this->phone) ? preg_replace('/[\s.\-()]/', '', $this->phone) : null,
            'experience_years' => is_string($this->experience_years) ? str_replace(',', '.', $this->experience_years) : null,
        ], fn ($value) => $value !== null));
    }

    public function rules(): array
    {
        $name = ['required', 'string', 'min:2', 'max:80', "regex:/^[\p{L}\p{M}\s'’.-]+$/u"];

        return [
            'website' => ['prohibited'], // champ piège invisible : seul un robot le remplit

            'first_name' => $name,
            'last_name' => $name,
            'email' => ['required', 'string', 'max:190', 'email:rfc,strict', Rule::unique('candidates', 'email')],
            'phone' => ['nullable', 'string', 'regex:/^\+?\d{8,15}$/'],
            'city' => ['nullable', 'string', 'max:80'],
            'country' => ['required', 'string', 'max:80'],
            'linkedin_url' => ['nullable', 'string', 'max:300', 'url:http,https', $this->hostIs(['linkedin.com'], 'LinkedIn')],
            'github_url' => ['nullable', 'string', 'max:300', 'url:http,https', $this->hostIs(['github.com', 'gitlab.com'], 'GitHub ou GitLab')],
            'portfolio_url' => ['nullable', 'string', 'max:300', 'url:http,https'],

            'education_level' => ['required', Rule::enum(EducationLevel::class)],
            'education_field' => ['required', Rule::enum(EducationField::class)],
            'institution' => ['nullable', 'string', 'max:150'],
            'graduation_year' => ['nullable', 'integer', 'between:1970,'.(now()->year + 6)],

            'experience_years' => ['required', 'numeric', 'min:0', 'max:40'],
            'experience_type' => ['required', Rule::enum(ExperienceType::class)],
            'projects_description' => ['nullable', 'string', 'max:2000'],
            'motivation' => ['nullable', 'string', 'max:3000'],

            'availability' => ['required', Rule::enum(Availability::class)],
            'available_from' => ['nullable', 'date_format:Y-m-d'],

            'skills' => ['required', 'array', 'min:1', 'max:20'],
            'skills.*.id' => ['required', 'integer', 'distinct', Rule::exists('skills', 'id')],
            'skills.*.level' => ['required', Rule::enum(SkillLevel::class)],

            'consent' => ['accepted'],
        ];
    }

    /** Contrôles de cohérence entre champs. */
    public function after(): array
    {
        return [
            function (Validator $validator) {
                if ($this->input('experience_type') === ExperienceType::Aucune->value && (float) $this->input('experience_years') > 0) {
                    $validator->errors()->add('experience_type', "Incohérent : vous indiquez des années d'expérience mais aucun type d'expérience.");
                }
            },
        ];
    }

    public function messages(): array
    {
        return [
            'website.prohibited' => 'Soumission rejetée.',
            'required' => ':Attribute est obligatoire.',
            'string' => ':Attribute doit être un texte.',
            'min.string' => ':Attribute doit contenir au moins :min caractères.',
            'max.string' => ':Attribute ne doit pas dépasser :max caractères.',
            'regex' => ':Attribute n\'est pas dans un format valide.',
            'email' => 'Adresse email invalide.',
            'email.unique' => self::DUPLICATE_EMAIL,
            'url' => ':Attribute doit être une adresse web valide (http ou https).',
            'enum' => 'La valeur choisie pour :attribute n\'est pas valide.',
            'integer' => ':Attribute doit être un nombre entier.',
            'numeric' => ':Attribute doit être un nombre.',
            'between.numeric' => ':Attribute doit être compris entre :min et :max.',
            'min.numeric' => ':Attribute ne peut pas être inférieur à :min.',
            'max.numeric' => ':Attribute ne peut pas dépasser :max.',
            'date_format' => ':Attribute doit être une date valide (AAAA-MM-JJ).',
            'skills.required' => 'Sélectionnez au moins une compétence.',
            'skills.min' => 'Sélectionnez au moins une compétence.',
            'skills.max' => 'Vous ne pouvez pas sélectionner plus de :max compétences.',
            'skills.*.id.exists' => 'Compétence inexistante.',
            'skills.*.id.distinct' => 'Une compétence est sélectionnée deux fois.',
            'skills.*.level.required' => 'Indiquez votre niveau pour chaque compétence.',
            'skills.*.level.enum' => 'Niveau de compétence invalide.',
            'consent.accepted' => 'Votre accord sur le traitement des données est nécessaire.',
        ];
    }

    public function attributes(): array
    {
        return [
            'first_name' => 'le prénom', 'last_name' => 'le nom', 'email' => 'l\'email', 'phone' => 'le téléphone',
            'city' => 'la ville', 'country' => 'le pays', 'linkedin_url' => 'le lien LinkedIn',
            'github_url' => 'le lien GitHub', 'portfolio_url' => 'le lien du portfolio',
            'education_level' => 'le niveau d\'études', 'education_field' => 'le domaine de formation',
            'institution' => 'l\'établissement', 'graduation_year' => 'l\'année d\'obtention',
            'experience_years' => 'le nombre d\'années d\'expérience', 'experience_type' => 'le type d\'expérience',
            'projects_description' => 'la description des projets', 'motivation' => 'la motivation',
            'availability' => 'la disponibilité', 'available_from' => 'la date de disponibilité',
        ];
    }

    /** Vérifie le domaine d'un lien (évite « github.com.site-pirate.com »). */
    private function hostIs(array $domains, string $name): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail) use ($domains, $name) {
            $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));
            foreach ($domains as $domain) {
                if ($host === $domain || str_ends_with($host, '.'.$domain)) {
                    return;
                }
            }
            $fail("Le lien doit pointer vers $name.");
        };
    }
}
