# Cahier des charges — Talent Engine

**Système de qualification et de classement des candidatures**

---

## 1. Présentation du projet

### 1.1 Contexte

SKULLVI reçoit régulièrement des candidatures pour ses programmes de développement de talents et
d'accompagnement professionnel. Quand le nombre de profils augmente, le tri manuel devient long et
inégal. Il faut un outil qui centralise les candidatures, analyse certains critères et aide les
responsables à identifier rapidement les profils prioritaires.

### 1.2 Problématique

Comment permettre à SKULLVI de :

- recevoir les candidatures ;
- centraliser les informations essentielles ;
- vérifier la complétude des profils ;
- qualifier automatiquement certains critères ;
- attribuer un score aux candidats ;
- classer les candidatures ;
- identifier les profils à examiner en priorité ;
- permettre à un responsable de consulter le détail d'un candidat ?

---

## 2. Objectifs

**Objectif général** : concevoir une première version fonctionnelle d'un système qui collecte,
qualifie, score et classe automatiquement des candidatures.

**Objectifs spécifiques** : le système permet de :

1. créer une candidature ;
2. enregistrer les informations du candidat ;
3. recueillir ses compétences (avec un niveau) et ses expériences ;
4. identifier les critères recherchés ;
5. calculer automatiquement un score ;
6. attribuer une catégorie ;
7. classer les candidats ;
8. identifier les profils prioritaires ;
9. permettre à un responsable de consulter une candidature ;
10. conserver la justification du score.

---

## 3. Périmètre du MVP

| Fonctionnalité | Priorité | Statut |
|---|---:|---|
| Formulaire de candidature | Haute | ✅ |
| Validation des données | Haute | ✅ |
| Enregistrement de la candidature | Haute | ✅ |
| Gestion des compétences (avec niveau) | Haute | ✅ |
| Calcul du score | Haute | ✅ |
| Classement des candidats | Haute | ✅ |
| Catégorisation | Haute | ✅ |
| Tableau de classement | Haute | ✅ |
| Consultation du profil | Haute | ✅ |
| Tests du système de scoring | Haute | ✅ |
| Authentification administrateur | Moyenne | ✅ |
| Suivi de la revue (statut et note du responsable) | Moyenne | ✅ |
| Alertes pour le responsable (pépite, doublon, etc.) | Bonus | ✅ |
| Export du classement en CSV (Excel) | Moyenne | ✅ |
| Export de la fiche candidat en PDF | Moyenne | ✅ |
| IA pour analyser les CV | Hors MVP | — |
| Envoi automatique d'e-mails | Hors MVP | — |

Le but n'est pas de construire un ATS complet, mais de démontrer une logique de conception claire.

> **Choix** : la modification d'une candidature par le responsable se limite au suivi de la revue
> (statut, note). Les données déclarées par le candidat et le score ne sont pas modifiables à la main,
> pour que le score reste fidèle à la candidature reçue.

---

## 4. Acteurs

| Acteur | Peut… |
|---|---|
| **Candidat** | accéder au formulaire, renseigner ses informations, sa formation, ses compétences et leur niveau, son expérience, ses réalisations et sa disponibilité, puis soumettre |
| **Responsable** | se connecter, consulter le classement et les compteurs, filtrer, consulter une fiche et la justification du score, mettre à jour le statut de revue, exporter le classement en CSV et chaque fiche en PDF |
| **Système** | valider, normaliser, détecter les doublons, calculer le score, catégoriser, classer, signaler les alertes |

---

## 5. Informations collectées

| Bloc | Champs (obligatoires en **gras**) |
|---|---|
| Identité | **Prénom**, **nom**, **email**, téléphone, ville, **pays** |
| Liens | LinkedIn, GitHub/GitLab, portfolio |
| Formation | **Niveau d'études**, **domaine de formation** (liste fermée), établissement, année d'obtention |
| Compétences | **Au moins une**, chacune avec un **niveau** : débutant / intermédiaire / avancé |
| Expérience | **Années d'expérience**, **type** (professionnelle, freelance, académique, personnelle, aucune), description des projets réalisés, motivation |
| Disponibilité | **Temps plein / temps partiel**, date de disponibilité (vide = immédiate) |
| Consentement | **Accord sur le traitement des données** |

Volontairement **non collectés** : photo, âge, genre, nationalité. Ces informations n'ont pas à
influencer une présélection.

---

## 6. Processus fonctionnel

```
Candidat → Formulaire → Validation et normalisation → Contrôle des doublons → Enregistrement
        → Qualification → Calcul du score (+ justification) → Catégorisation → Classement
        → Profils prioritaires et alertes → REVUE PAR LE RESPONSABLE
```

---

## 7. Système de scoring

Le score est simple, transparent et explicable : 100 points répartis en 6 critères.

| Critère | Points maximum |
|---|---:|
| Formation pertinente | 15 |
| Compétences techniques | 30 |
| Expérience | 20 |
| Qualité des réalisations | 15 |
| Disponibilité | 10 |
| Complétude du profil | 10 |
| **Total** | **100** |

---

## 8. Catégories

| Score | Catégorie | Action |
|---:|---|---|
| 80–100 | ⭐ Prioritaire | À examiner rapidement |
| 65–79 | 🟢 Intéressant | À examiner |
| 50–64 | 🟡 À approfondir | Analyse complémentaire |
| 0–49 | 🔴 Faible priorité | À conserver / revoir |

> **Le score ne décide pas du recrutement.** Il aide le responsable à prioriser sa lecture.
> Pour cette raison, le candidat ne voit pas son score.

---

## 9. Règles de scoring

Toutes ces valeurs sont paramétrables dans `backend/config/talent.php`.

**Formation (15)** : selon le domaine de formation.

| Pertinence | Domaines | Points |
|---|---|---:|
| Forte | Informatique / génie logiciel, data / IA | 15 |
| Partielle | Réseaux / télécoms, mathématiques / physique / électronique, gestion des SI, **bootcamp / autoformation** | 10 |
| Faible | Autre domaine | 5 |

**Compétences techniques (30)** : pour chaque compétence *recherchée* par SKULLVI.

| Niveau | Points |
|---|---:|
| Avancé | +5 |
| Intermédiaire | +3 |
| Débutant | +1 |

Plafond de 30 points. Les compétences non recherchées (WordPress, Figma, etc.) ne rapportent pas de
points mais sont signalées au responsable.

**Expérience (20)**

| Expérience | Points |
|---|---:|
| 3 ans et plus | 20 |
| 2 ans | 15 |
| 1 an | 10 |
| Moins d'un an | 5 |
| Aucune | 0 |

**Qualité des réalisations (15)**

| Élément | Points |
|---|---:|
| Lien GitHub ou GitLab | +5 |
| Portfolio | +4 |
| Projets décrits en détail (150 caractères ou plus) | +6 |
| Projets décrits brièvement (50 caractères ou plus) | +3 |

**Disponibilité (10)** : temps plein 10, temps partiel 6. Retrait de 3 points si le candidat n'est
disponible qu'après le démarrage du programme.

**Complétude du profil (10)** : part des 10 champs facultatifs renseignés.

| Complétude | Points |
|---|---:|
| 100 % | 10 |
| 80 % ou plus | 8 |
| 60 % ou plus | 6 |
| Moins de 60 % | 3 |

**Classement** : score décroissant, puis qualité des réalisations, puis date de dépôt.

---

## 10. Gestion des erreurs

Le système empêche ou signale :

| Cas | Message |
|---|---|
| Email invalide (`laure@@gmail`) | « Adresse email invalide. » |
| Champ obligatoire manquant | « Le prénom est obligatoire. », etc. |
| Doublon d'email | « Une candidature associée à cette adresse email existe déjà. » |
| Compétence inexistante | « Compétence inexistante. » |
| Expérience négative | « Le nombre d'années d'expérience ne peut pas être inférieur à 0. » |
| Données incohérentes | « Incohérent : vous indiquez des années d'expérience mais aucun type d'expérience. » |
| Faux lien GitHub | « Le lien doit pointer vers GitHub ou GitLab. » |
| Soumission multiple accidentelle | Bouton désactivé pendant l'envoi + contrainte d'unicité en base |
| Score supérieur à 100 | Impossible : chaque critère est borné à son maximum, et le total à [0, 100] |

---

## 11. Gestion des doublons

- **Email** : vérifié avant l'enregistrement, sans tenir compte de la casse (`Aicha@…` = `aicha@…`) et garanti par une contrainte `UNIQUE` en base, y compris en cas d'envois simultanés.
- **Téléphone** : un numéro déjà utilisé n'est pas bloquant (un foyer peut partager un numéro), mais la candidature est signalée « doublon possible ».

---

## 12. Tableau de bord

- Compteurs : total, prioritaires, intéressants, à approfondir, faible priorité (cliquables pour filtrer).
- Tableau : rang, candidat, score, catégorie, alertes, statut de revue.
- Filtres : catégorie, compétence, disponibilité, niveau d'études, statut de revue, recherche par nom, email ou ville (et score minimum via l'API).
- Le rang affiché reste le rang global, même quand la liste est filtrée.
- **Export CSV** du classement affiché (filtres compris), ouvert directement par Excel : séparateur `;`, accents, une colonne par critère du score.
- Sur chaque ligne : accès à la **fiche** et téléchargement direct de la fiche en **PDF**.

---

## 13. Fiche candidat

- En-tête : nom, rang, score sur 100, catégorie et action recommandée.
- **Détail du score** : chaque critère avec ses points, son maximum et une explication en français.
- Points d'attention (alertes).
- Compétences avec leur niveau.
- Profil, liens, projets réalisés, motivation.
- Revue : statut (nouveau, en revue, entretien prévu, retenu, refusé) et note interne.
- **Téléchargement de la fiche en PDF** (A4) : même contenu, mention « document confidentiel », date de génération. Utile pour un entretien ou un comité de sélection.

---

## 14. Modèle de données

| Table | Colonnes principales |
|---|---|
| `candidates` | id, first_name, last_name, email (unique), phone, city, country, linkedin_url, github_url, portfolio_url, education_level, education_field, institution, graduation_year, experience_years, experience_type, projects_description, motivation, availability, available_from, **score**, **category**, projects_points, flags, scoring_version, review_status, reviewer_note, timestamps |
| `skills` | id, name (unique), is_sought |
| `candidate_skills` | id, candidate_id, skill_id, **level** — unique (candidate_id, skill_id) |
| `score_details` | id, candidate_id, criterion, label, points, max_points, **explanation**, created_at |

La table `score_details` conserve la justification de chaque score.

---

## 15. Architecture technique

```
React 19 + Vite + Tailwind CSS
          │  API REST (JSON, jeton Sanctum)
          ▼
Laravel 13 — Form Requests · Services · Eloquent
          │
          ▼
MySQL 8
```

---

## 16. API

| Méthode | Endpoint | Accès |
|---|---|---|
| `POST` | `/api/candidates` | public |
| `GET` | `/api/skills`, `/api/options` | public |
| `POST` | `/api/login`, `/api/logout` | — |
| `GET` | `/api/candidates` (filtres : `category`, `min_score`, `skill`, `availability`, `education_level`, `review_status`, `search`) | responsable |
| `GET` | `/api/candidates/ranking` | responsable |
| `GET` | `/api/candidates/export` (mêmes filtres) | responsable |
| `GET` | `/api/candidates/{id}` | responsable |
| `GET` | `/api/candidates/{id}/pdf` | responsable |
| `PATCH` | `/api/candidates/{id}` (statut de revue, note) | responsable |

---

## 17. Tests

61 tests automatisés (PHPUnit), dont les 5 scénarios de référence :

1. **Candidature valide** : créée, score calculé, catégorie attribuée, 6 lignes de justification.
2. **Email invalide** (`laure@@gmail`) : validation échouée, aucune candidature créée.
3. **Doublon** : email existant (casse différente comprise), rejet.
4. **Calcul du score** : barème de chaque critère, total égal à la somme du détail, 89 → Prioritaire.
5. **Classement** : A = 89, B = 76, C = 92 → 1. C, 2. A, 3. B.

Plus : seuils à chaque frontière de catégorie, plafond des compétences, 300 profils aléatoires toujours
entre 0 et 100, contrôle d'accès, limitation de débit, recalcul après changement de règles, exports CSV et PDF.

---

## 18. Évolutions possibles

- **Phase 2** : notifications e-mail, comptes responsables multiples, historique des revues, export PDF groupé.
- **Phase 3** : barème par programme, import de CV et extraction des informations, matching candidat / offre.
- **Phase 4** : analyse des candidatures par IA, en suggestion uniquement, avec explication et validation humaine.

---

## 19. Sécurité et confidentialité

- Validation et normalisation de toutes les entrées côté serveur.
- Requêtes via l'ORM Eloquent (protection contre l'injection SQL).
- Espace responsable protégé par jeton (Laravel Sanctum, expiration après 12 h), mots de passe hachés.
- Limitation de débit : 5 tentatives de connexion par minute, 10 candidatures par minute et par IP, avec des compteurs distincts.
- Champ piège contre les robots ; liens limités à `http(s)` et domaines vérifiés pour GitHub et LinkedIn.
- Pas de données sensibles collectées ; consentement obligatoire ; score non exposé au candidat.
- Exports réservés au responsable connecté ; protection contre l'injection de formules dans le CSV ; PDF marqué « confidentiel ».

---

## 20. Critères de réussite

| Critère | Atteint |
|---|---|
| Une candidature peut être créée | ✅ |
| Les données sont validées | ✅ |
| Les informations sont stockées | ✅ |
| Les compétences sont associées au candidat, avec leur niveau | ✅ |
| Le score est calculé automatiquement, entre 0 et 100 | ✅ |
| Une catégorie est attribuée | ✅ |
| Les candidats sont classés | ✅ |
| Les profils prioritaires sont facilement identifiables | ✅ |
| Les règles de scoring sont compréhensibles | ✅ |
| Les principaux cas d'erreur sont gérés | ✅ |
| Les tests passent | ✅ (61/61) |
| Le projet s'installe à partir du README | ✅ |
