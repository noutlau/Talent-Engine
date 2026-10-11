# Talent Engine

**Système de qualification et de classement des candidatures — Mini-Challenge SKULLVI**

> Talent Engine ne remplace pas le recruteur. Il lui fournit un outil d'aide à la décision qui réduit
> le temps consacré au tri initial et met en évidence les candidatures à examiner en priorité.

```
Candidat ──► Formulaire React ──► API Laravel ──► Validation ──► Enregistrement (MySQL)
                                                                      │
Responsable ◄── Tableau de bord ◄── Classement ◄── Catégorie ◄── Score + justification
```

| | |
|---|---|
| **Frontend** | React 19 + Vite + Tailwind CSS 4 + React Router |
| **Backend** | Laravel 13 (API REST, Form Requests, Eloquent, Sanctum) |
| **Base de données** | MySQL 8 (SQLite en mémoire pour les tests) |
| **Exports** | Classement en CSV (compatible Excel), fiche candidat en PDF (dompdf) |
| **Tests** | PHPUnit : 61 tests, 32 unitaires sur le moteur de scoring et 29 sur l'API |

Le cahier des charges complet est dans [docs/CAHIER_DES_CHARGES.md](docs/CAHIER_DES_CHARGES.md).

---

## Installation

Prérequis : PHP 8.3+, Composer, Node 20+, MySQL 8 (fourni par [Laragon](https://laragon.org)).

**1. Base de données.** Démarrer MySQL (Laragon › *Démarrer tout*) puis créer la base :

```sql
CREATE DATABASE talent_engine CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

**2. API Laravel**

```bash
cd backend
composer install
cp .env.example .env          # DB_DATABASE=talent_engine, utilisateur root sans mot de passe (Laragon)
php artisan key:generate
php artisan migrate --seed    # tables + compétences + compte responsable + 9 candidats de démonstration
php artisan serve             # http://127.0.0.1:8000
```

**3. Interface React** (dans un second terminal)

```bash
cd frontend
npm install
npm run dev                   # http://localhost:5173
```

| Page | URL |
|---|---|
| Formulaire de candidature | http://localhost:5173 |
| Espace responsable | http://localhost:5173/dashboard (`admin@skullvi.test` / `skullvi-demo`) |

**Tests**

```bash
cd backend
php artisan test
```

---

## Architecture

```
backend/
  config/talent.php                  ← TOUTES les règles de scoring (barèmes, seuils, champs de complétude)
  app/Enums/                         ← listes fermées : niveaux, domaines, disponibilités, catégories…
  app/Http/Requests/StoreCandidateRequest.php   ← validation + messages d'erreur en français
  app/Services/Scoring/ScoringEngine.php        ← calcul du score (sans accès base : testable isolément)
  app/Services/CandidateService.php             ← pipeline : enregistrer → compétences → scorer → justifier
  app/Http/Controllers/Api/          ← endpoints REST
  app/Exports/CandidatesCsv.php      ← une ligne de l'export CSV (+ protection contre les formules Excel)
  resources/views/pdf/candidate.blade.php  ← mise en page de la fiche PDF
  app/Models/                        ← Candidate, Skill, ScoreDetail
  database/migrations/               ← 4 tables métier
  tests/Unit/ScoringEngineTest.php   ← le barème, critère par critère
  tests/Feature/CandidateApiTest.php ← l'API de bout en bout
frontend/src/
  pages/ApplyPage.jsx                ← formulaire candidat
  pages/DashboardPage.jsx            ← compteurs, filtres, classement, export CSV, PDF par ligne
  pages/CandidatePage.jsx            ← fiche + détail du score + revue + téléchargement PDF
  api.js, auth.jsx                   ← client API, authentification par jeton
```

**Choix structurants**

- **Les règles sont dans la configuration, pas dans le code.** Un barème va évoluer. On modifie
  `config/talent.php`, on incrémente `version`, puis `php artisan talent:rescore` recalcule toutes les
  candidatures. Chaque score garde la version des règles qui l'a produit.
- **Le moteur de scoring est isolé.** Il reçoit un candidat et renvoie un résultat, sans écrire en
  base. Il se teste donc sans base de données et se lit comme le barème du cahier des charges.
- **Le score est toujours justifié.** La table `score_details` conserve une ligne par critère, avec
  les points et une explication en français. Le responsable voit *pourquoi* un candidat a 66 et non 80.
- **Les listes de choix viennent du backend** (`/api/options`, `/api/skills`). Le frontend n'a rien en dur :
  ajouter une compétence ou un domaine se fait à un seul endroit.
- **La validation côté serveur fait foi.** Le formulaire React affiche les messages renvoyés par
  Laravel. On ne maintient pas deux jeux de règles qui pourraient diverger.

---

## Modèle de données

```
candidates ──< candidate_skills >── skills
     │              (level)          (is_sought)
     └──< score_details
          (criterion, points, max_points, explanation)
```

| Table | Rôle |
|---|---|
| `candidates` | Identité, formation, expérience, disponibilité, puis `score`, `category`, `flags` (alertes), `scoring_version`, `review_status`, `reviewer_note` |
| `skills` | Catalogue des compétences. `is_sought` indique si la compétence est recherchée (seules celles-ci rapportent des points) |
| `candidate_skills` | Compétences d'un candidat **avec son niveau** (débutant / intermédiaire / avancé) |
| `score_details` | La justification du score : une ligne par critère |

Contraintes : `email` unique, couple `(candidate_id, skill_id)` unique, suppression en cascade des compétences et du détail du score.

---

## Système de scoring (sur 100)

| Critère | Max | Règle |
|---|---:|---|
| **Formation** | 15 | Domaine fortement pertinent (informatique, data/IA) 15 · partiellement (réseaux, sciences, gestion SI, **autoformation/bootcamp**) 10 · autre 5 |
| **Compétences techniques** | 30 | Pour chaque compétence *recherchée* : avancé +5, intermédiaire +3, débutant +1. Plafond 30 |
| **Expérience** | 20 | 3 ans et plus 20 · 2 ans 15 · 1 an 10 · moins d'un an 5 · aucune 0 |
| **Qualité des réalisations** | 15 | Lien GitHub/GitLab +5 · portfolio +4 · projets décrits en détail (150 caractères ou plus) +6, brièvement (50 ou plus) +3 |
| **Disponibilité** | 10 | Temps plein 10 · temps partiel 6 · −3 si disponible après le démarrage du programme |
| **Complétude du profil** | 10 | Part des 10 champs facultatifs remplis : 100 % → 10 · 80 % → 8 · 60 % → 6 · moins → 3 |

### Catégories

| Score | Catégorie | Action |
|---:|---|---|
| 80–100 | ⭐ Prioritaire | À examiner rapidement |
| 65–79 | 🟢 Intéressant | À examiner |
| 50–64 | 🟡 À approfondir | Analyse complémentaire |
| 0–49 | 🔴 Faible priorité | À conserver / revoir |

**Classement** : score décroissant ; à égalité, la meilleure *qualité des réalisations* passe devant
(ce qu'on a fait départage ce qu'on déclare) ; puis le premier arrivé. Le rang est calculé par une
fonction de fenêtre SQL (`ROW_NUMBER()`) : il reste le rang **global** même quand la liste est filtrée.

### Alertes : ce que le score ne sait pas voir

Les alertes ne modifient pas le score. Elles attirent l'attention du responsable :

- **Pépite potentielle** : réalisations solides (12/15 ou plus) mais score global modeste. Le système signale ses propres angles morts.
- **Aucune réalisation présentée** : à demander au candidat plutôt que de l'écarter.
- **Doublon possible** : même numéro de téléphone qu'une autre candidature.
- **Disponible après le démarrage** du programme.
- **Compétences hors critères** (WordPress, Flutter…) : à évaluer par un humain.

### Choix assumés

- **Un autodidacte n'est pas pénalisé comme un profil hors sujet.** L'autoformation est classée « partiellement pertinente ».
- **Le domaine de formation est une liste fermée**, pas un texte libre. La pertinence est alors déterministe et explicable.
- **Seules les compétences recherchées rapportent des points.** Les autres restent visibles et signalées.
- **Le candidat ne voit pas son score.** Ce n'est pas une décision, et il ne doit pas être lu comme tel.
- **Aucune donnée sensible n'est collectée** : ni photo, ni âge, ni genre, ni nationalité. Le pays sert au contact, pas au score.

---

## API

| Méthode | Endpoint | Accès | Rôle |
|---|---|---|---|
| `GET` | `/api/skills` | public | Compétences du formulaire |
| `GET` | `/api/options` | public | Listes de choix (niveaux, domaines, catégories…) |
| `POST` | `/api/candidates` | public, 10/min | Dépôt d'une candidature |
| `POST` | `/api/login` | public, 5/min | Connexion responsable, renvoie un jeton Sanctum (12 h) |
| `GET` | `/api/candidates` | responsable | Liste classée et paginée. Filtres : `category`, `min_score`, `skill`, `availability`, `education_level`, `review_status`, `search` |
| `GET` | `/api/candidates/ranking` | responsable | Tableau de bord : total, nombre de candidats par catégorie, tête du classement |
| `GET` | `/api/candidates/export` | responsable | Le classement en CSV, **avec les mêmes filtres** que la liste |
| `GET` | `/api/candidates/{id}` | responsable | Fiche complète avec `score_details` |
| `GET` | `/api/candidates/{id}/pdf` | responsable | La fiche candidat en PDF (A4) |
| `PATCH` | `/api/candidates/{id}` | responsable | Statut de revue et note interne (le score n'est pas modifiable) |
| `POST` | `/api/logout` | responsable | Révoque le jeton |

---

## Gestion des erreurs

| Cas | Comportement | Test |
|---|---|---|
| Email invalide (`laure@@gmail`) | 422 « Adresse email invalide. », rien n'est créé | ✓ |
| Champs obligatoires manquants | 422, un message par champ | ✓ |
| Doublon d'email (y compris `AICHA@…` / `aicha@…`) | 422 « Une candidature associée à cette adresse email existe déjà. » | ✓ |
| Double clic / envois simultanés | Bouton désactivé pendant l'envoi + contrainte `UNIQUE` en base, transformée en 422 | ✓ |
| Compétence inexistante, niveau invalide, compétence en double | 422 | ✓ |
| Expérience négative ou supérieure à 40 ans | 422 | ✓ |
| Données incohérentes (« aucune expérience » mais 2 ans) | 422 | ✓ |
| Faux lien GitHub (`github.com.site-pirate.com`), lien `javascript:` | 422 | ✓ |
| Score hors bornes | Chaque critère est borné à son maximum, total borné à [0, 100] ; vérifié sur 300 profils aléatoires | ✓ |
| Robot (champ piège rempli) | 422 | ✓ |
| Accès au tableau de bord sans jeton | 401 | ✓ |
| Force brute sur la connexion | 429 après 5 essais par minute, compteur **distinct** de celui du formulaire | ✓ |
| Formats variés (« 2,5 » ans, téléphone avec espaces) | Normalisés avant validation | ✓ |
| Injection de formule dans l'export (un nom valant `=HYPERLINK(…)`) | Cellule préfixée par `'` : Excel l'affiche comme du texte au lieu de l'exécuter | ✓ |
| API arrêtée | Message clair côté interface | — |

---

## Tests

```bash
cd backend && php artisan test
```

**Unitaires, moteur de scoring (32)** : les maximums totalisent 100, profil idéal = 100, total = somme du détail,
barème de chaque critère (formation, niveaux de compétence, plafond de 30, paliers d'expérience,
réalisations, disponibilité et pénalité de démarrage tardif, complétude), **seuils des catégories testés
à chaque frontière** (80/79, 65/64, 50/49), pépite, et 300 profils aléatoires toujours compris entre 0 et 100.

**API (29)** : les 5 scénarios du cahier des charges (candidature valide, email invalide, doublon,
calcul du score, classement A=89 / B=76 / C=92 → C, A, B), départage à score égal, filtres qui conservent
le rang global, authentification, limitation de débit, mise à jour de la revue, recalcul après changement de règles,
export CSV (BOM pour Excel, ordre du classement, filtres respectés) et PDF (type, nom de fichier, 404).

Les tests tournent sur SQLite en mémoire (rapide, sans configuration). Ils ont aussi été exécutés avec succès sur MySQL 8.4.

---

## Limites et évolutions

Volontairement hors MVP, mais l'architecture peut les accueillir :

- **Phase 2** : e-mail d'accusé de réception, plusieurs comptes responsables, historique des changements de statut, export PDF groupé.
- **Phase 3** : un barème par programme (le moteur lit déjà une configuration : il suffirait d'en avoir une par programme), import et extraction de CV.
- **Phase 4** : analyse par IA (résumé, compétences détectées), toujours **en suggestion** relue par un humain, jamais comme décision.
- **Calibrage** : comparer les scores aux décisions réelles des responsables pour ajuster les barèmes.
- **RGPD** : durée de conservation, suppression sur demande.

Le dossier `archive/prototype-php/` contient un premier prototype en PHP natif. Il a servi à valider la logique avant le passage à Laravel + React.

---

## Utilisation de l'IA

J'ai utilisé l'IA comme un binôme de développement, et je préfère être transparent sur la répartition du travail.

**Ce que l'IA a fait**

- **Cahier des charges** : [À COMPLÉTER : par exemple « une première version a été rédigée avec un assistant IA à partir de ma compréhension du besoin, puis je l'ai relue et ajustée »].
- **Code et documentation** : l'essentiel du code (API Laravel, interface React, moteur de scoring, tests) et de ce README a été
  généré avec **Claude Code** (Anthropic), un agent de programmation qui écrit le code, l'exécute et le teste.
  Il a d'abord produit un prototype en PHP natif (dossier `archive/`), puis j'ai demandé de le refaire sur ma stack.

**Ce que j'ai fait**

- **Le choix de la stack** : Laravel + API REST + React + Tailwind + MySQL, des technologies que je veux maîtriser et défendre.
- **Le cadrage** : le cahier des charges, la grille de scoring sur 100 et les catégories, que l'IA a ensuite implémentés tels quels.
- **Les demandes fonctionnelles** : l'export CSV du classement et la fiche candidat en PDF.
- **L'installation et les essais** dans mon environnement (Laragon, MySQL), y compris le diagnostic d'un conflit de port.
- [À COMPLÉTER : ce que vous avez modifié vous-même dans le code, ce que vous avez vérifié ou testé à la main, les choix que vous avez remis en question.]
- [À COMPLÉTER : ce que ce projet vous a appris, par exemple les fonctions de fenêtre SQL pour le rang, les Form Requests Laravel, les limiteurs de débit, l'injection de formules dans un CSV.]

**Mon regard sur cette méthode**

[À COMPLÉTER en 2 ou 3 phrases : pourquoi vous avez travaillé ainsi, ce que l'IA accélère, ce qu'elle ne remplace pas (comprendre le besoin, décider des critères, vérifier le résultat).]
