<?php

require_once '../vendor/autoload.php';
use PhpAmqpLib\Connection\AMQPStreamConnection;

$connection = new AMQPStreamConnection('localhost', 5672, 'guest', 'guest');
$channel = $connection->channel();

$channel->exchange_declare('time_jobs', 'direct', false, false, false);

list($queue_name, ,) = $channel->queue_declare("", false, false, true, false);

$channel->queue_bind($queue_name, 'time_jobs', 'cron_frame'); // IMPORTANT

$callback = function ($msg) {
    /*
     * начало битриксового cron_frame (он может быть ошибочным, это концепт)
     */

    $_SERVER["DOCUMENT_ROOT"] = realpath(__DIR__) . '/../../../../'; // replace #DOCUMENT_ROOT# to real document root path
    $DOCUMENT_ROOT = $_SERVER["DOCUMENT_ROOT"];

    $siteID = 's1'; // replace #SITE_ID# to your real site ID - need for language ID

    define("NO_KEEP_STATISTIC", true);
    define("NOT_CHECK_PERMISSIONS",true);
    define("BX_CAT_CRON", true);
    define('NO_AGENT_CHECK', true);
    if (preg_match('/^[a-z0-9_]{2}$/i', $siteID) === 1)
    {
        define('SITE_ID', $siteID);
    }
    else
    {
        die('No defined site - $siteID');
    }

    require($_SERVER["DOCUMENT_ROOT"]."/bitrix/modules/main/include/prolog_before.php");

    global $DB;

    if (!defined('LANGUAGE_ID') || preg_match('/^[a-z]{2}$/i', LANGUAGE_ID) !== 1)
        die('Language id is absent - defined site is bad');

    set_time_limit(0);

    if (!\Bitrix\Main\Loader::includeModule('catalog'))
        die('Can\'t include module');

    $profile_id = 0;
    if (isset($msg->body))
        $profile_id = (int)$msg->body;
    if ($profile_id <= 0)
        die('No profile id');

    $ar_profile = CCatalogExport::GetByID($profile_id);
    if (!$ar_profile)
        die('No profile');

    $strFile = CATALOG_PATH2EXPORTS.$ar_profile["FILE_NAME"]."_run.php";
    if (!file_exists($_SERVER["DOCUMENT_ROOT"].$strFile))
    {
        $strFile = CATALOG_PATH2EXPORTS_DEF.$ar_profile["FILE_NAME"]."_run.php";
        if (!file_exists($_SERVER["DOCUMENT_ROOT"].$strFile))
            die('No export script');
    }

    $arSetupVars = array();
    $intSetupVarsCount = 0;
    if ($ar_profile["DEFAULT_PROFILE"] != 'Y')
    {
        parse_str($ar_profile["SETUP_VARS"], $arSetupVars);
        if (!empty($arSetupVars) && is_array($arSetupVars))
            $intSetupVarsCount = extract($arSetupVars, EXTR_SKIP);
    }

    $firstStep = true;

    global $arCatalogAvailProdFields;
    $arCatalogAvailProdFields = CCatalogCSVSettings::getSettingsFields(CCatalogCSVSettings::FIELDS_ELEMENT);
    global $arCatalogAvailPriceFields;
    $arCatalogAvailPriceFields = CCatalogCSVSettings::getSettingsFields(CCatalogCSVSettings::FIELDS_CATALOG);
    global $arCatalogAvailValueFields;
    $arCatalogAvailValueFields = CCatalogCSVSettings::getSettingsFields(CCatalogCSVSettings::FIELDS_PRICE);
    global $arCatalogAvailQuantityFields;
    $arCatalogAvailQuantityFields = CCatalogCSVSettings::getSettingsFields(CCatalogCSVSettings::FIELDS_PRICE_EXT);
    global $arCatalogAvailGroupFields;
    $arCatalogAvailGroupFields = CCatalogCSVSettings::getSettingsFields(CCatalogCSVSettings::FIELDS_SECTION);

    global $defCatalogAvailProdFields;
    $defCatalogAvailProdFields = CCatalogCSVSettings::getDefaultSettings(CCatalogCSVSettings::FIELDS_ELEMENT);
    global $defCatalogAvailPriceFields;
    $defCatalogAvailPriceFields = CCatalogCSVSettings::getDefaultSettings(CCatalogCSVSettings::FIELDS_CATALOG);
    global $defCatalogAvailValueFields;
    $defCatalogAvailValueFields = CCatalogCSVSettings::getDefaultSettings(CCatalogCSVSettings::FIELDS_PRICE);
    global $defCatalogAvailQuantityFields;
    $defCatalogAvailQuantityFields = CCatalogCSVSettings::getDefaultSettings(CCatalogCSVSettings::FIELDS_PRICE_EXT);
    global $defCatalogAvailGroupFields;
    $defCatalogAvailGroupFields = CCatalogCSVSettings::getDefaultSettings(CCatalogCSVSettings::FIELDS_SECTION);
    global $defCatalogAvailCurrencies;
    $defCatalogAvailCurrencies = CCatalogCSVSettings::getDefaultSettings(CCatalogCSVSettings::FIELDS_CURRENCY);

    CCatalogDiscountSave::Disable();
    include($_SERVER["DOCUMENT_ROOT"].$strFile);
    CCatalogDiscountSave::Enable();

    CCatalogExport::Update(
        $profile_id,
        array(
            "=LAST_USE" => $DB->GetNowFunction()
        )
    );


    /*
     * конец битриксового cron_frame
     */
};

$channel->basic_consume($queue_name, '', false, true, false, false, $callback);

while ($channel->is_consuming()) {
    $channel->wait();
}

$channel->close();
$connection->close();
