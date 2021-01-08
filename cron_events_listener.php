<?php

require_once '../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->exchange_declare('time_jobs', 'direct', false, false, false);

list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);

$channel->queue_bind($queue_name, 'time_jobs', 'cron_events'); // IMPORTANT

$callback = function ($msg) {
    /*
     * начало битриксового cron_events (он может быть ошибочным, это концепт)
     */

    $_SERVER["DOCUMENT_ROOT"] = realpath(dirname(__FILE__)."/../..");
    $DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

    define("NO_KEEP_STATISTIC", true);
    define("NOT_CHECK_PERMISSIONS",true);
    define('BX_NO_ACCELERATOR_RESET', true);
    define('CHK_EVENT', true);
    define('BX_WITH_ON_AFTER_EPILOG', true);

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

    @set_time_limit(0);
    @ignore_user_abort(true);

    CAgent::CheckAgents();
    define("BX_CRONTAB_SUPPORT", true);
    define("BX_CRONTAB", true);
    CEvent::CheckEvents();

    if(CModule::IncludeModule('sender'))
    {
        \Bitrix\Sender\MailingManager::checkPeriod(false);
        \Bitrix\Sender\MailingManager::checkSend();
    }

    require($_SERVER['DOCUMENT_ROOT']."/bitrix/modules/main/tools/backup.php");
    CMain::FinalActions();


    /*
     * конец битриксового cron_events
     */
};

$channel->basic_consume($queue_name, '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
