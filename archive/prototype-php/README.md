# Talent Engine

Première version fonctionnelle d'un moteur de candidatures pour SKULLVI :

**recevoir → collecter → qualifier → scorer → classer → prioriser**

Une application web simple : un formulaire public pour les candidats, un espace recruteur
qui affiche le classement et explique chaque score. PHP 8 + SQLite, sans framework ni
dépendance : elle se lance en une commande.

---

## Démarrage rapide

Prérequis : PHP 8.2 ou plus avec l'extension `pdo_sqlite` (fournie avec Laragon, XAMPP, etc.).

```bash
php bin/import.php data/sample.csv --reset   # charge 13 candidatures fictives (dont 2 invalides, rejetées volontairement)
php -S localhost:8000 -t public              # lance le serveur
php tests/run.php                            # lance les 35 tests
```

| Page | URL |
|---|---|
| Formulaire candidat | http://localhost:8000 |
| Espace recruteur | http://localhost:8000/admin.php (mot de passe démo : `skullvi-demo`) |

---

## 1. Comment j'ai découpé le problème

Avant d'écrire du code, j'ai reformulé le besoin en questions :

| Étape | Question | Réponse dans le projet |
|---|---|---|
| Recevoir | D'où viennent les candidatures ? | Formulaire web **et** import CSV (exports Google Forms ou Excel). Les deux passent par le **même pipeline**. |
| Collecter | Quelles informations sont vraiment utiles pour décider ? | 19 champs, dont 12 obligatoires. Pas de photo, d'âge, de genre ni de nationalité (voir §5). |
| Qualifier | Qu'est-ce qui rend un profil inéligible, quel que soit son talent ? | 2 critères éliminatoires : niveau de français, disponibilité. |
| Scorer | Comment comparer des profils différents ? | Score sur 100, réparti en 6 critères pondérés, **chaque point est expliqué**. |
| Classer | Comment départager les ex æquo ? | Qualifiés d'abord, puis score, puis preuves concrètes, puis ancienneté de la candidature. |
| Prioriser | Que doit lire le recruteur en premier ? | 4 niveaux (Prioritaire, À examiner, En attente, Non qualifié) et des **alertes** pour les cas que le score ne capte pas. |

Principe directeur : **le score aide à prioriser la lecture, il ne décide pas.** Un humain relit
chaque dossier ; l'outil lui indique par où commencer et pourquoi.

---

## 2. Architecture

```
config/scoring.php        ← toutes les règles métier (poids, seuils, compétences, programmes)
src/
  Validator.php           ← valide et normalise une candidature brute
  Scorer.php              ← qualifie et score (fonction pure, sans base ni horloge)
  Ranker.php              ← classe les candidats
  ApplicationService.php  ← le pipeline : valider → dédoublonner → scorer → stocker
  Repository.php          ← SQLite (seul fichier qui contient du SQL)
  Csv.php                 ← lecture CSV, export sécurisé
public/                   ← pages web (formulaire, connexion, classement, fiche, export)
bin/import.php            ← import CSV en ligne de commande
bin/rescore.php           ← recalcule tous les scores après un changement de règles
tests/                    ← 35 tests sans dépendance
```

```
Formulaire web ─┐
                ├─► Validator ─► Doublon ? ─► Scorer ─► Repository ─► Ranker ─► Tableau de bord / Export CSV
Import CSV ─────┘      │             │
                       ▼             ▼
                 erreurs par      refus clair
                 champ
```

**Pourquoi ces choix ?**

- **Règles dans un fichier de config, pas dans le code.** Les critères de recrutement vont changer.
  Un recruteur peut modifier un poids ou une compétence attendue, incrémenter `version`, lancer
  `php bin/rescore.php` : tous les scores sont recalculés, et chaque score garde la version des règles qui l'a produit.
- **Le scorer est une fonction pure.** Même entrée, même résultat : c'est facile à tester et à expliquer.
- **Un profil par programme.** Le même moteur score le programme Développeurs et le programme Data & IA
  avec des compétences attendues différentes. Ajouter un programme, c'est ajouter un bloc de config.
- **PHP + SQLite sans framework.** Pour un premier livrable, la priorité est que n'importe qui puisse
  le lancer et le lire en quelques minutes. Le découpage en classes permettrait de passer à Laravel
  et MySQL sans réécrire la logique.

---

## 3. Modèle de données

Une table `candidates`. Les informations du candidat, le résultat du scoring et le suivi de la revue sont séparés logiquement :

| Groupe | Colonnes |
|---|---|
| Identité | `first_name`, `last_name`, `email`, `phone`, `country`, `city` |
| Candidature | `program`, `education`, `experience_years`, `skills` (JSON), `other_skills`, `availability`, `available_from`, `french_level`, `english_level`, `portfolio_url`, `project_description`, `motivation` |
| Résultat calculé | `score`, `tier`, `qualified`, `breakdown` (JSON : détail par critère), `flags` (JSON), `disqualifications` (JSON), `scoring_version` |
| Revue humaine | `review_status` (nouveau → en revue → entretien → retenu / refusé), `reviewer_note` |
| Technique | `id`, `created_at`, `updated_at`, contrainte `UNIQUE(email, program)` |

Le détail du score est stocké, pas seulement le total : la fiche candidat peut ainsi afficher
*pourquoi* un profil a obtenu 67 et non 80.

---

## 4. Logique de qualification et de scoring

### Critères éliminatoires (qualification)

| Critère | Règle (programme Dev) | Pourquoi |
|---|---|---|
| Français | niveau ≥ B1 | Le programme se déroule en français. |
| Disponibilité | au moins à temps partiel (20 h/semaine) | En dessous, le programme ne peut pas être suivi. |

Un profil éliminé **garde son score** : le recruteur voit qu'un candidat est excellent mais
non éligible, et peut décider d'une dérogation (par exemple un francophone A2 très avancé techniquement).

### Score sur 100

| Critère | Poids | Calcul |
|---|---|---|
| Compétences techniques | 30 | 80 % : compétences clés possédées / 3 attendues (pas besoin de toutes les connaître) · 20 % : compétences bonus (3 max) |
| Preuves concrètes | 20 | 50 % : lien (GitHub/GitLab = 100 %, portfolio = 80 %) · 50 % : projet décrit (≥ 150 caractères = 100 %, ≥ 50 = 50 %) |
| Parcours | 20 | 70 % : expérience (plafonnée à 4 ans) · 30 % : formation |
| Motivation | 15 | 60 % : longueur · 40 % : thèmes attendus abordés (apprendre, équipe, contribuer…) |
| Disponibilité | 10 | Temps plein 100 %, temps partiel 60 % |
| Langues | 5 | Français 60 %, anglais 40 % |

**Choix assumés :**

- **Les preuves pèsent autant que le parcours.** Un lien GitHub ou un projet décrit montre
  ce que la personne sait *faire*. C'est aussi le premier critère de départage à score égal.
- **La formation pèse peu (6 points sur 100).** Un autodidacte avec de l'expérience réelle ne doit pas être
  écarté par un diplôme. C'est cohérent avec l'esprit du programme (« évoluer au-delà d'une seule spécialité »).
- **L'expérience est plafonnée à 4 ans.** Au-delà, l'écart ne dit plus grand-chose pour un programme de formation.
- **La motivation est un signal faible et le dit.** Longueur et mots-clés sont faciles à contourner :
  ce critère ne compte que pour 15 points et la fiche invite explicitement à lire le texte.

### Seuils de priorité

| Niveau | Condition |
|---|---|
| **Prioritaire** | qualifié et score ≥ 70 |
| **À examiner** | qualifié et 50 ≤ score < 70 |
| **En attente** | qualifié et score < 50 |
| **Non qualifié** | au moins un critère éliminatoire |

### Alertes (n'influencent pas le score, orientent la revue)

- **Pépite potentielle** : preuves concrètes solides malgré un score global modeste. Le système signale ses propres angles morts.
- **Aucune preuve concrète** : à demander au candidat plutôt que de l'écarter.
- **Doublon possible** : même téléphone qu'une autre candidature.
- **Disponible après le démarrage** du programme.
- **Compétences hors catalogue** (Flutter, Kubernetes…) à évaluer par un humain.

---

## 5. Erreurs anticipées et sécurité

| Risque | Réponse |
|---|---|
| Données incomplètes ou mal formées | Validation champ par champ, messages en français, saisie conservée dans le formulaire |
| Formats variés (« 2,5 » ans, téléphones avec espaces, e-mails en majuscules) | Normalisation avant stockage |
| Doublons | `UNIQUE(email, program)` en base, vérifiée aussi avant insertion (message clair) ; téléphone en double signalé |
| Double envoi / rafraîchissement | Redirection après envoi (Post/Redirect/Get), contrainte unique en dernier recours |
| Spam | Champ piège invisible (honeypot) |
| Injection SQL | Requêtes préparées uniquement, SQL confiné à `Repository.php` |
| XSS | Données stockées brutes, échappées à l'affichage ; liens limités à `http(s)` (pas de `javascript:`) |
| CSRF | Jeton sur tous les formulaires |
| Injection de formules dans l'export Excel | Cellules commençant par `= + - @` neutralisées |
| Paramètres d'URL inattendus | Valeurs filtrées sur des listes autorisées |
| Accès au classement | Espace recruteur protégé par mot de passe, session régénérée à la connexion |
| CSV d'Excel français | Séparateur `;` et BOM détectés automatiquement |

**Équité et données personnelles :** le formulaire ne collecte ni photo, ni âge, ni genre, ni
nationalité. Le pays sert au contact, pas au score. Le consentement est obligatoire et le score n'est
jamais montré au candidat, pour éviter qu'il soit lu comme une décision.

---

## 6. Tests

```bash
php tests/run.php
```

35 tests, sans framework (un mini-lanceur tenant sur une page), répartis en trois groupes :

- **Validator** (12) : champs obligatoires, normalisation, e-mails et liens invalides, `javascript:`, dates impossibles, honeypot, tableaux injectés.
- **Scorer** (12) : les poids totalisent 100, profil idéal = 100, total = somme du détail affiché, critères éliminatoires,
  seuils inclusifs, **monotonie** (ajouter une compétence ne fait jamais baisser le score), faux domaine `github.com.evil.example`,
  pépite, et **500 profils aléatoires** pour vérifier que le score reste toujours entre 0 et 100.
- **Pipeline** (11) : stockage complet sur SQLite en mémoire, doublons, rescoring après changement de règles, ordre de classement, export CSV, import avec `;` et BOM.

---

## 7. Limites et suites possibles

Volontairement hors périmètre de cette première version :

- **Notification** : e-mail d'accusé de réception au candidat, alerte au recruteur pour chaque profil prioritaire.
- **Plusieurs recruteurs** : comptes individuels, historique des changements de statut.
- **Calibrage du scoring** : comparer les scores aux décisions réelles des recruteurs pour ajuster les poids.
- **Motivation** : une analyse par IA (résumé, points forts) pourrait aider, mais uniquement en suggestion relue par un humain.
- **Vérification des liens** : contrôler qu'un dépôt GitHub existe et contient du code récent.
- **RGPD** : durée de conservation, suppression sur demande, export des données d'un candidat.
- **Volume** : le classement est calculé en PHP, ce qui suffit pour quelques milliers de candidatures ; au-delà, on le ferait en SQL.

---

## 8. Utilisation de l'IA

> **À compléter par le candidat.** Expliquez honnêtement comment l'IA a été utilisée
> (par exemple : génération du code, rédaction, relecture) et votre contribution personnelle :
> choix des critères et de leurs poids, vérifications effectuées, modifications apportées, ce que vous avez appris.
