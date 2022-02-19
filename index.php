<?

require_once($_SERVER['DOCUMENT_ROOT'] . "/parser/settings.php");
require_once ($_SERVER['DOCUMENT_ROOT'] . "/parser/debugger.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/bitrix/modules/main/include/prolog_before.php");
require_once($_SERVER['DOCUMENT_ROOT'] . "/parser/function.php");

set_time_limit(0);
ini_set('max_execution_time', 100000);
ini_set('memory_limit', '2048M');

use Bitrix\Main\Application;

CModule::includeModule("sale");
\Bitrix\Main\Loader::includeModule('iblock');
\Bitrix\Main\Loader::includeModule('catalog');
use \Bitrix\Catalog\ProductTable;

//============================ XML ===========================//
// Каталог товаров из XML
$arrCatalogInXml = json_decode(json_encode(simplexml_load_file(FILE_CATALOG)), TRUE);
// Группы(Разделы)
$xmlSection = $arrCatalogInXml['Классификатор']['Группы']['Группа'];
// Товары
$xmlElement = $arrCatalogInXml['Каталог']['Товары'];

// Торговые предложения из XML
$arrOffersInXml = json_decode(json_encode(simplexml_load_file(FILE_OFFERS)), TRUE);
//$xmlOffers = $arrOffersInXml->ИзмененияПакетаПредложений->Предложения->Предложение;
$xmlOffers = $arrOffersInXml['ПакетПредложений']['Предложения']['Предложение'];


//============================ IBLOCK ===========================//
// Массив всех категорий(разделов)
$getSectionSelect = array("ID", "NAME", "EXTERNAL_ID", "IBLOCK_SECTION_ID");
$getSectionFilter = array("IBLOCK_ID" => IBLOCK_ID, "GLOBAL_ACTIVE" => "Y");
$massivSection = getSection($getSectionSelect, $getSectionFilter);

// Массивы для получения элементов инфоблока
$getElementSelect = array("ID", "NAME", "XML_ID", "IBLOCK_SECTION_ID", "CML2_ARTICLE" => "CML2_ARTICLE");
$getElementFilter = array('IBLOCK_ID' => IBLOCK_ID, "ACTIVE" => "Y");

// Массив всех свойств каталога
$getPropertyFilter = array('IBLOCK_ID' => IBLOCK_ID, "ACTIVE" => "Y");

// Массив всех свойств торговых предложений
$getPropertyFilterOffer = array('IBLOCK_ID' => IBLOCK_ID, "ACTIVE" => "Y");

//Добавления или обновления раздела
$count = 0;
setGroupsRecursive($xmlSection, 0, $massivSection, IBLOCK_ID, TRANS_SUMBOL_CODE, $count);

//Получает новые создавшиеся разделы
$massivSection = getSection($getSectionSelect, $getSectionFilter);
//Получает существующие элементы инфоблока
$iblockElem = getElementBlock($getElementSelect, $getElementFilter);
//Получает существующие тороговые предложения
$iblockOffer = getOffer(IBLOCK_TORG);
//Существующие свойства в торговых предложениях
$propOffer = getProperty(['IBLOCK_ID' => IBLOCK_TORG, "ACTIVE" => "Y"]);

//Создает новые элементы и тороговые предложения
setElementsAndOffers($iblockElem, $xmlElement, $iblockOffer, $xmlOffers,  $massivSection, $propOffer);