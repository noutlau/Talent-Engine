<?php
declare(strict_types=1);

/** Recalcule tous les scores après une modification de config/scoring.php. Usage : php bin/rescore.php */

use TalentEngine\ApplicationService;
use TalentEngine\Repository;
use TalentEngine\Scorer;
use TalentEngine\Validator;

require __DIR__ . '/../src/bootstrap.php';

$appConfig = require __DIR__ . '/../config/app.php';
$scoring = require __DIR__ . '/../config/scoring.php';
$service = new ApplicationService(new Validator($scoring), new Scorer($scoring), Repository::open($appConfig['db_path']));

printf("%d candidature(s) rescorée(s) avec les règles v%s.\n", $service->rescoreAll(), $scoring['version']);
