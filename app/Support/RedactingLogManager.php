<?php

declare(strict_types=1);

namespace App\Support;

use Illuminate\Log\Logger;
use Illuminate\Log\LogManager;
use Monolog\Logger as Monolog;

/**
 * مدير السجلات بحجب البيانات الشخصية في كل قناة (PersonalDataRedactor): تمرّ كل
 * قناة يُنشئها Laravel بهذه الدالة، ومنها القنوات المجمَّعة (stack) وقناة Laravel
 * Cloud التي تُعرَّف عند الإقلاع لا في config/logging.php.
 */
class RedactingLogManager extends LogManager
{
    /**
     * @param  string  $name
     */
    protected function tap($name, Logger $logger): Logger
    {
        $logger = parent::tap($name, $logger);
        $monolog = $logger->getLogger();

        if ($monolog instanceof Monolog) {
            $monolog->pushProcessor(new PersonalDataRedactor);
        }

        return $logger;
    }
}
