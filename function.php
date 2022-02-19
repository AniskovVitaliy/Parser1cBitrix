<?

function translit_sef($value)
{
    $converter = array(
        'а' => 'a', 'б' => 'b', 'в' => 'v', 'г' => 'g', 'д' => 'd',
        'е' => 'e', 'ё' => 'e', 'ж' => 'zh', 'з' => 'z', 'и' => 'i',
        'й' => 'y', 'к' => 'k', 'л' => 'l', 'м' => 'm', 'н' => 'n',
        'о' => 'o', 'п' => 'p', 'р' => 'r', 'с' => 's', 'т' => 't',
        'у' => 'u', 'ф' => 'f', 'х' => 'h', 'ц' => 'c', 'ч' => 'ch',
        'ш' => 'sh', 'щ' => 'sch', 'ь' => '', 'ы' => 'y', 'ъ' => '',
        'э' => 'e', 'ю' => 'yu', 'я' => 'ya',
    );

    $value = mb_strtolower($value);
    $value = strtr($value, $converter);
    $value = mb_ereg_replace('[^-0-9a-z]', '_', $value);
    $value = mb_ereg_replace('[-]+', '_', $value);
    $value = trim($value, '_');

    return $value;
}

// вывод разделов
function getSection($select,$filter){
    $db_list = CIBlockSection::GetList(Array("SORT"=>"ASC"), $filter,false,$select);
    while($ar_result = $db_list->GetNext())
    {
        $arDBgroup[$ar_result['EXTERNAL_ID']] = $ar_result;
    }

    return $arDBgroup;
}

//Парсер раздела рекурсии
function setGroupsRecursive($arrGroupsInXml, $idGroup, $arDBgroup, $IBLOCK_ID, $transSumbolCode, $count){
    foreach ($arrGroupsInXml as $key => $item) {
        
        //Поиск по существующим разделам
        $arFields = array(
            "NAME" => $item['Наименование'],
            "EXTERNAL_ID" => $item['Ид'],
            "IBLOCK_SECTION_ID" => $idGroup
        );

        //Формирования массива для обновления/добавления разделов
        $arFildsSection = array(
            "IBLOCK_SECTION_ID" => $arDBgroup[$arFields["IBLOCK_SECTION_ID"]]['ID'],
            "EXTERNAL_ID" => $item['Ид'],
            "NAME" => $item['Наименование'],
            "IBLOCK_ID" => $IBLOCK_ID,
            "CODE" => translit_sef($item['Наименование']).$count,
        );

        //Получения всех секций
        $section = new CIBlockSection;
        if($arDBgroup[$arFields['EXTERNAL_ID']]){
            $result = $section->Update($arDBgroup[$arFields['EXTERNAL_ID']]['ID'], $arFildsSection);
        }else{
            $result = $section->Add($arFildsSection);
        }
        $root = $item['Группы']['Группа'];
        if (!empty($root)) {
            $count++;
            getGroupsRecursive($root, $item['Ид'],getSection(Array("ID", "NAME", "EXTERNAL_ID","IBLOCK_SECTION_ID"),Array('IBLOCK_ID'=>$IBLOCK_ID, 'GLOBAL_ACTIVE'=>'Y')),$IBLOCK_ID,$transSumbolCode,$count);
        }

    }
}

// получения элементов инфоблока
function getElementBlock($getElementSelect,$getElementFilter){

    return $elements = \Bitrix\Iblock\Elements\ElementCatalogTable::getList([
        'select' => $getElementSelect,
        'filter' => $getElementFilter,
    ])->fetchAll();

}

function getOffer($IBLOCK_TORG){

    $elemObjOffer = \Bitrix\Iblock\ElementTable::getList(array(
        'filter' => array('IBLOCK_ID' => $IBLOCK_TORG),
        'select' => array("ID", "NAME", "XML_ID", "CODE"),
    ));

    while ($offer = $elemObjOffer->fetch()) {
        $elemOffer[$offer['XML_ID']] = array(
            'ID' => $offer['ID'], 
            'CODE' => $offer,
        );
    }

    return $elemOffer;
}

// вывод всех свойств
function getProperty($arFilter){
    $properties = CIBlockProperty::GetList(Array("sort"=>"asc", "name"=>"asc"), $arFilter);
    while ($prop_fields = $properties->GetNext())
    {
      $propertyValue[$prop_fields['XML_ID']] = $prop_fields;
      $res = CIBlockProperty::GetPropertyEnum($propertyValue[$prop_fields['XML_ID']]['ID'] , Array(), Array());
         while ($prop_enum = $res->GetNext())
            {
              $propertyValue[$prop_fields['XML_ID']]['ENUM'][] = $prop_enum;
            }       
    }
    return $propertyValue;
}

// вывод всех значений списочных свойств
function getPropertyEnum($IBLOCK_ID,$idProperty){
    $enum = [];
    $property_enums = CIBlockPropertyEnum::GetList(Array("DEF"=>"DESC", "SORT"=>"ASC"), Array("IBLOCK_ID"=>$IBLOCK_ID, "PROPERTY_ID"=>$idProperty));
    while($enum_fields = $property_enums->GetNext()){
         $enum[$enum_fields['VALUE']] = $enum_fields;
    }
    return $enum;
}

//Записывает элементы и тороговые предложения из XML
function setElementsAndOffers($iblockElements, $xmlElem, $iblockOffers, $xmlOffers, $massiveSection, $propList){

    //Переменная для итератора, итерируется в конце цикла
    $i=1;
    //Массив для сверки
    $searchMass = [];

    $arCatalog = CCatalog::GetByID(IBLOCK_TORG);
    $SKUProperty = $arCatalog['SKU_PROPERTY_ID']; // ID свойства в инфоблоке предложений типа "Привязка к товарам (SKU)"

    $obElement = new CIBlockElement();

    //Переписываем массив существующих товаров в инфоблоке, записываем в ключ XML_ID
    $iblockElements = rewriteArr($iblockElements);

    foreach ($xmlElem['Товар'] as $xmlKey => $xmlProduct) {

        //Делим ID на: (id - товара) и (id - торогового предложения)
        $productAndTorgID = explode('#', trim($xmlProduct['Ид']));

        //Если такого товара небыло в цикле
        if(!$searchMass[$productAndTorgID[0]]){

            //Удаления из имени дополнительных надписей торговых предложений наример - (beige, 2B)
            $clipName = trim(preg_replace('/[(].*[)]$/ui', '', trim($xmlProduct['Наименование'])));

            //Формирование массива для обновления/добавления елементов товара
            $PROP[114] = $xmlProduct['Артикул'];
            $arFildsElement = array(
                "EXTERNAL_ID" => $productAndTorgID[0],
                "IBLOCK_SECTION_ID" => $massiveSection[$xmlProduct['Группы']['Ид']]['ID'],
                "NAME" => $clipName,
                "IBLOCK_ID" => IBLOCK_ID,
                "PROPERTY_VALUES"=> $PROP,
                "DATE_ACTIVE_FROM" => ConvertTimeStamp(time(), "SHORT"),
                "CODE" => translit_sef($clipName) . "_$i"
            );

            //Если в существующем инфоблоке нет товара
            if(!$iblockElements[$productAndTorgID[0]]){
                //Добавляем товар
                $intProductID = $obElement->Add($arFildsElement);
                echo '<span style="color:#009900">Доб тов</span> [' . $intProductID . ' / ' . $productAndTorgID[0] . ']<br>';
            }else{
                //Обновлен товар
                $intProductID = $obElement->Update($iblockElements[$productAndTorgID[0]]["ID"], $arFildsElement);
                echo '<span style="color:#FF6600">Обн тов</span> [' . $iblockElements[$productAndTorgID[0]]["ID"] . ' / ' . $productAndTorgID[0] . ']<br>';
            }

            //Если товар был добавлен оставляем ID товара, если обновлен, то в переменную intProductID записывает id обновленного товара
            $intProductID = $intProductID != 1 ? $intProductID : $iblockElements[$productAndTorgID[0]]["ID"];

            //Заполняем множествунную строку с дополнительным полем :описание
            $cml2Traits = $xmlProduct['ЗначенияРеквизитов']['ЗначениеРеквизита'];
            if($cml2Traits) setPropertyWithDesc($intProductID, $cml2Traits, 116);

            //Записываем массив для сверки
            $searchMass[$productAndTorgID[0]] = $intProductID;
        }else{
            //Если такой товар уже был в цикле, то получаем его ID из массива
            $intProductID = $searchMass[$productAndTorgID[0]];
        }

        //Если есть XML_ID торгового предложения
        if(isset($productAndTorgID[1])){

            //Формируем массив для торговых предложений
            $arProp[$SKUProperty] = $intProductID;
            $arFields = array(
                "EXTERNAL_ID" => $productAndTorgID[1],
                'NAME' => $xmlProduct['Наименование'],
                'IBLOCK_ID' => IBLOCK_TORG,
                'ACTIVE' => 'Y',
                'PROPERTY_VALUES' => $arProp,
                "DATE_ACTIVE_FROM" => ConvertTimeStamp(time(), "SHORT"),
                "CODE" => translit_sef($xmlProduct['Наименование']) . "_$i"
            );

            //Есть ли тороговое предложение в инфоблоке
            if(!$iblockOffers[$productAndTorgID[1]]){
                //Добавляем тороговое предложение
                $intOfferID = $obElement->Add($arFields);
                echo '<span style="color:#00FF00">Доб тор.пр.</span> [' . $intProductID . ' / ' . $productAndTorgID[0] . '] # [' . $intOfferID . ' / ' . $productAndTorgID[1] . ']<br>';
            }else{
                //Обновляет тороговое предложение
                $intOfferID = $obElement->Update($iblockOffers[$productAndTorgID[1]]['ID'], $arFields);
                echo '<span style="color:#FFCC00">Обн тор.пр.</span> [' . $intProductID . ' / ' . $productAndTorgID[0]. '] # [' . $iblockOffers[$productAndTorgID[1]]['ID'] . ' / ' . $productAndTorgID[1] . ']<br>';
            }

            //Если тор.пр. было добавлено оставляем ID товара, если обновлено, то в переменную intOffersID записывает id обновленного тор.пр.
            $intOfferID = $intOfferID != 1 ? $intOfferID : $iblockOffers[$productAndTorgID[1]]['ID'];

            $hmlProperty = $xmlProduct['ХарактеристикиТовара']['ХарактеристикаТовара'];

            //Получаем массив свойств
            $getProp = checkProperty($hmlProperty, $propList);

            //Получаем свойста
            foreach ($getProp as $itemProp){
                //Установит значение свойств, если такого значения не существует в списке, то добавит
                SetProperty($intOfferID, $itemProp['Value'], $itemProp['XML_ID'], $itemProp['ID']);
            }

            //Доступное кол-во
            addCountProduct($intOfferID, $productAndTorgID[1], $xmlOffers);

            add_price($intOfferID,1, $productAndTorgID[1],$xmlOffers);
        }

        $i++;
    }
    pr($searchMass);
}

function add_price($prod_id, $price_id, $code, $xmlOffers){

    foreach ($xmlOffers as $value){

        if(explode('#', $value['Ид'])[1] == $code){

            $arFields = Array(
                "PRODUCT_ID" => $prod_id,
                "CATALOG_GROUP_ID" => $price_id,
                "PRICE" => $value['Цены']['Цена']['ЦенаЗаЕдиницу'],
                "CURRENCY" => $value['Цены']['Цена']['Валюта']
            );
            $res = CPrice::GetList(
                array(),
                array(
                    "PRODUCT_ID" => $prod_id,
                    "CATALOG_GROUP_ID" => $price_id
                )
            );
            if ($arr = $res->Fetch()) {
                CPrice::Update($arr["ID"], $arFields);
            } else {
                CPrice::Add($arFields);
            }

            break;
        }
    }

}

function checkProperty($hmlProperty, $propList){
    $result = [];
    //Получаем свойства тороговых предложений (список) из xml и находим существующий список в инфоблоке
    foreach ($hmlProperty as $keyXMLProp => $valueXMLProp) {

        foreach ($propList as $propListVal) {

            if(trim($valueXMLProp['Наименование']) == trim($propListVal['NAME'])){

                $result[$keyXMLProp]['Value'] = $valueXMLProp['Значение'];
                $result[$keyXMLProp]['XML_ID'] = $valueXMLProp['Ид'];
                $result[$keyXMLProp]['ID'] = $propListVal['ID'];

                break;
            }
        }
    }
    return $result;
}

//Переписывает массив, в ключи заносим XML_ID
function rewriteArr($arr){
    $newIblockElements = [];
    foreach ($arr as $key => $value) {
        $newIblockElements[$value['XML_ID']] = $value;
    }
    return $newIblockElements;
}

//Устанавливает значния для мн списков с доп полем :описание
function setPropertyWithDesc($intProductID, $cml2Traits, $propID){

    $arrTraits = [];

    foreach ($cml2Traits as $keyTrait => $valueTrait) {
        $arrTraits[$keyTrait] = [
            'VALUE' => $valueTrait['Наименование'],
            'DESCRIPTION' => $valueTrait['Значение']
        ];
    }

    CIBlockElement::SetPropertyValuesEx($intProductID, false, [$propID => $arrTraits]);
}

//Устанавливает значения списочным свойствам свойствам
function SetProperty($elemID, $valueProp, $hmlPropID, $propID){
    
    if($valueProp){
        $PropList = getPropertyEnum(IBLOCK_TORG, $propID);

        //Если нужное значение сущ, иначе добавляет новое свойство
        if($PropList[$valueProp]){
            //Устанавливает значения свойства
            CIblockElement::SetPropertyValuesEx($elemID, false, [$propID => $PropList[$valueProp]['ID']]);
        }else{
            $code_prop = translit_sef($valueProp);
            $addProp = new CIBlockPropertyEnum();
            $valueId = $addProp->Add([
                'PROPERTY_ID' => $propID,
                'VALUE' => $valueProp,
                'XML_ID' => $hmlPropID .'-'. $code_prop,
            ]);
            //Устанавливает значения свойства
            CIblockElement::SetPropertyValuesEx($elemID, false, [$propID => $valueId]);
        }
    }

}

//Добавляет доступное кол-во торговому предложению
function addCountProduct($intOfferID, $xmlID, $xmlOffers){

    foreach ($xmlOffers as $value){

        if(explode('#', $value['Ид'])[1] == $xmlID){

            CCatalogProduct::Add(
                array(
                    "ID" => $intOfferID,
                    "QUANTITY" => $value['Количество']
                )
            );

            break;
        }

    }

}




