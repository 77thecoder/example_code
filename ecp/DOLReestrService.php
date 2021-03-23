<?php

namespace App\Services\DOL;

use App\DOLCert;
use App\Http\Requests\DOL\DOLReestrAddContractRequest;
use App\Http\Requests\DOL\DOLReestrGetListRequest;
use App\Http\Requests\DOL\DOLReestrNewRequest;
use App\Services\Ticket\TicketInterface;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Config;

class DOLReestrService implements DOLReestrInterface
{
    /**
     * Создать новый реестр
     * Возвращает url созданного реестра
     * @param DOLReestrNewRequest $request
     * @return JsonResponse
     */
    public function new(DOLReestrNewRequest $request)
    {
        $params = $request->all();
        $params['method'] = 'GET';
        $params['url'] = Config::get('services.dol_reestr.new');

//        $response = DOLReestrHttpRequest::httpRequest($params);
        $response = DOLReestrHttpRequestService::curlRequest($params);

        if ($response['info']['http_code'] == 200) {
            // забираем валидаторы из полученной страницы
            $validators1 = $this->getValidators($response['body']);

            // частично заполняем форму: указываем валидаторы с предыдущего шага, тип реестра Ручной (Hand)
            // после отправки этих данных форма перезагружается и становится доступен тип конрактов FTTB
            $params['validators1'] = $validators1;
            $params = $this->validator2Request($params);
            $response = DOLReestrHttpRequestService::curlRequest($params, true);

            if ($response['info']['http_code'] == 200) {
                // забираем валидаторы из полученной страницы
                $validators2 = $this->getValidators($response['body']);

                // полностью заполняем форму: указываем валидаторы с предыдущего шага, тип реестра Ручной (Hand),
                // система оплаты "С предоплатой" (3), тип контрактов "FTTB" (4), номер сейф-пакета и примечание,
                // слово "Сохранить"
                $params['validators2'] = $validators2;
                $params = $this->validator3Request($params);
                $response = DOLReestrHttpRequestService::curlRequest($params, true);

                // если ответ 200 и есть ID то возвращаем урл нового реестра
                if (
                    $response['info']['http_code'] == 200
                    && preg_match("/\|\d+@contractid/", $response['info']['url'])
                ) {
                    // забираем валидаторы из полученной страницы
//                    $this->getValidators($response['body']);
                    return response()->json([
                        'result' => 'success',
                        'url' => $response['info']['url']
                    ]);
                } else {
                    return response()->json([
                        'message' => 'Ошибка создания реестра',
                        'detail' => $response['error'],
                        'request_uri' => $response['info']['url']
                    ], $response['info']['http_code']);
                }
            } else {
                return response()->json([
                    'message' => 'Ошибка создания реестра',
                    'detail' => $response['error'],
                    'request_uri' => $response['info']['url']
                ], $response['info']['http_code']);
            }
        } else {
            return response()->json([
                'message' => 'Ошибка создания реестра',
                'detail' => $response['error'],
                'request_uri' => $response['info']['url']
            ], $response['info']['http_code']);
        }
    }

    /**
     * Получаем валидаторы из ответа сервиса DOL
     * @param $body
     * @param bool $findCPoint
     * @return array
     */
    private function getValidators($body, $findCPoint = false)
    {
        $response = [];
        $flagFindCPoint = 0;
        $validatorsArr = explode("\r\n", $body);

        foreach ($validatorsArr as $string) {
            if (preg_match("/id=\"__VIEWSTATEGENERATOR\"/", $string)) {
                $generator = preg_replace("/^.*value=\"/", "", $string);
                $response['generator'] = trim(preg_replace("/\".*$/", "", $generator));
            }
            if (preg_match("/id=\"__VIEWSTATE\"/", $string)) {
                $view = preg_replace("/.*value=\"/", "", $string);
                $response['view'] = trim(preg_replace("/\".*$/", "", $view));
            }
            if (preg_match("/id=\"__EVENTVALIDATION\"/", $string)) {
                $event = preg_replace("/.*value=\"/", "", $string);
                $response['event'] = trim(preg_replace("/\".*$/", "", $event));
            }
            if ($findCPoint) {
                switch ($flagFindCPoint) {
                    case 0:
                        if (
                            preg_match("/select\.\*_ctl0:_ctl0:sDealerAndPoint:ddPoints/", $string)
                            && !$flagFindCPoint
                        ) {
                            $flagFindCPoint++;
                        }
                        break;

                    case 1:
                        $flagFindCPoint++;
                        break;

                    case 2:
                        $response['cPoint'] = preg_replace("/.*val.*=\"/", "", $string);
                        break;

                    default:
                        break;
                }
            }
        }

        return $response;
    }

    /**
     * Готовим запрос для получения validator2
     * @param array $params
     * @return array
     */
    private function validator2Request(array $params)
    {
        $params['method'] = 'POST';

        return array_merge(
            $params,
            [
                'postData' => [
                    '__EVENTTARGET' => '_ctl0$_ctl0$SelectContractType$ddContractType',
                    '__EVENTARGUMENT' => '',
                    '__LASTFOCUS' => '',
                    '__VIEWSTATE' => $params['validators1']['view'],
                    '__VIEWSTATEGENERATOR' => $params['validators1']['generator'],
                    '__EVENTVALIDATION' => $params['validators1']['event'],
                    '_ctl0:_ctl0:tbSafePacket' => '',
                    '_ctl0:_ctl0:ddeSource' => 'Hand',
                    '_ctl0:_ctl0:SelectPaySystem:ddPaySystem' => '-1',
                    '_ctl0:_ctl0:SelectContractType:ddContractType' => '-1',
                    '_ctl0:_ctl0:tbeDescription' => (isset($params['safe'])) ? $params['safe'] : '',
                    '_ctl0:_ctl0:hiddenContractType' => ''
                ]
            ]
        );
    }

    /**
     * Готовим запрос для получения validator3
     * @param array $params
     * @return array
     */
    private function validator3Request(array $params)
    {
        $params['method'] = 'POST';

        return array_merge(
            $params,
            [
                'postData' => array(
                    '__EVENTTARGET' => '',
                    '__EVENTARGUMENT' => '',
                    '__LASTFOCUS' => '',
                    '__VIEWSTATE' => $params['validators2']['view'],
                    '__VIEWSTATEGENERATOR' => $params['validators2']['generator'],
                    '__EVENTVALIDATION' => $params['validators2']['event'],
                    '_ctl0:_ctl0:ddeSource' => 'Hand',
                    '_ctl0:_ctl0:tbSafePacket' => (isset($params['safe'])) ? $params['safe'] : '',
                    '_ctl0:_ctl0:SelectPaySystem:ddPaySystem' => '3',
                    '_ctl0:_ctl0:SelectContractType:ddContractType' => '4',
                    '_ctl0:_ctl0:tbeDescription' => (isset($params['safe'])) ? $params['safe'] : '',
                    '_ctl0:_ctl0:bSave' => '%D1%EE%F5%F0%E0%ED%E8%F2%FC',// 'Сохранить',
                    '_ctl0:_ctl0:hiddenContractType' => ''
                )
            ]
        );
    }

    /**
     * Ищем новый реестр
     * Реестр, в котором контрактов меньше заданого значения к конфиге
     * @param DOLReestrGetListRequest $request
     * @return JsonResponse
     */
    public function getReestrNew(DOLReestrGetListRequest $request)
    {
        // день - без ведущего нуля
        $todayDay = date("j");
        $lastDay = $todayDay;

        // месяц - без ведущего нуля
        $todayMonth = date("n");
        $lastMonth = $todayMonth;

        // год - YYYY
        $todayYear = date("Y");
        $lastYear = $todayYear - 1;

        // если текущий год високосный и сегодня 29 февраля, то за прошлогоднюю дату берём 28 февраля
        if (date("L") && $todayDay == 29 && $todayMonth == 2) {
            $lastDay = 28;
            $lastMonth = 2;
        }

        // если текущий год не високосный и сегодня 1 марта, то проверяем прошлый год: если он високосный,
        // то число прошлого года 29 февраля
        if (!date("L") && $todayDay == 1 && $todayMonth == 3) {
            if (date("L", mktime(0, 0, 0, 1, 1, $lastYear))) {
                $lastDay = 29;
                $lastMonth = 2;
            }
        }

        $params = $request->all();
        $params['method'] = $request->getMethod();
        $params['url'] = Config::get('services.dol_reestr.search');

        // загружаем форму поиска реестров
        $response = DOLReestrHttpRequestService::curlRequest($params);

        if ($response['info']['http_code'] == 200) {
            $validators1 = $this->getValidators($response['body']);
        } else {
            return response()->json([
                'message' => 'Ошибка загрузки формы поиска в DOL',
                'detail' => $response['body'],
                'request_uri' => $response['info']['url'],
            ], $response['info']['http_code']);
        }

        $params['method'] = 'POST';
        $params['url'] = Config::get('services.dol_reestr.searchWithParams');

        $params = array_merge(
            $params,
            array(
                'postData' => array(
                    '__EVENTTARGET' => '',
                    '__EVENTARGUMENT' => '',
                    '__LASTFOCUS' => '',
                    '__VIEWSTATE' => $validators1['view'],
                    '__VIEWSTATEGENERATOR' => $validators1['generator'],
                    '__EVENTVALIDATION' => $validators1['event'],
                    '_ctl0:_ctl0:rblReestrType' => "Incomming",
                    '_ctl0:_ctl0:rblSource' => 'Hand',
                    '_ctl0:_ctl0:ReestrStatus:ddStatus' => "-1",
                    '_ctl0:_ctl0:rblFilter' => "0",
                    '_ctl0:_ctl0:PaySystem:ddPaySystem' => "-1",
                    '_ctl0:_ctl0:DateInOutLower:ddDay' => $lastDay,
                    '_ctl0:_ctl0:DateInOutLower:ddMonth' => $lastMonth,
                    '_ctl0:_ctl0:DateInOutLower:ddYear' => $lastYear,
                    '_ctl0:_ctl0:DateInOutHigh:ddDay' => $todayDay,
                    '_ctl0:_ctl0:DateInOutHigh:ddMonth' => $todayMonth,
                    '_ctl0:_ctl0:DateInOutHigh:ddYear' => $todayYear,
                    '_ctl0:_ctl0:ddDateType' => "0",
                    '_ctl0:_ctl0:tbNumber' => '',
                    '_ctl0:_ctl0:tbName' => '',
                    '_ctl0:_ctl0:tbArchiveStorage' => '',
                    '_ctl0:_ctl0:tbSafePacket' => '',
                    '_ctl0:_ctl0:ContractType:ddContractType' => "-1",
                    '_ctl0:_ctl0:ibSearch.x' => "30",
                    '_ctl0:_ctl0:ibSearch.y' => "9"
                )
            )
        );

        $response = DOLReestrHttpRequestService::curlRequest($params, true);

        if ($response['info']['http_code'] == 200) {
            $reestrsList = [];
            // флаг, по которому понимаем, что нашли реестр на странице и обрабатываем его
            $itemFlag = false;
            // индекс, по которому понимаем, какую колонку в таблице реестров обрабатываем
            $element = 0;
            // массив свойств найденного реестра (будем добавлять в список реестров $reestrsList)
            $reestr = [];

            $bodyArr = explode("\n", $response['body']);

            foreach ($bodyArr as $string) {
                $string = mb_convert_encoding($string, "UTF-8", "CP1251");

                // нашли тег, который открывает свойства реестра
                // поднимаем флаг, обнуляем индекс, очищаем массив свойств
                if (preg_match("/ListItem/", $string) && !$itemFlag) {
                    $itemFlag = true;
                    $element = 0;
                    $reestr = [];
                    continue;
                }

                // нашли тег, который закрывает свойства реестра (при поднятом флаге)
                // опускаем флаг, массив со свойствами реестров добавляем в общий список реестров
                if ($itemFlag && preg_match("/<\/tr>/", $string)) {
                    $itemFlag = false;
                    $reestrsList[] = $reestr;
                    continue;
                }

                // нашли тег колонки таблицы, при поднятом флаге
                // вынимаем из неё тело свойства, кладём в массив свойств реестра
                if ($itemFlag && preg_match("/<td/", $string)) {
                    switch ($element) {
                        case 0:
                            $reestr['url'] = 'https://' . $params['url'] . trim(preg_replace(
                                "/\" id=.*$/",
                                "",
                                preg_replace("/^.*href=\"/", "", $string)
                            ));
                            $reestr['number'] = trim(preg_replace(
                                "/<\/a>.*$/",
                                "",
                                preg_replace("/^.*\">/", "", $string)
                            ));
                            $element++;
                            break;

                        case 1:
                            $reestr['name'] = trim(preg_replace(
                                "/\&.*$/",
                                "",
                                preg_replace("/^.*SelectorReestr\">/", "", $string)
                            ));
                            $element++;
                            break;

                        case 2:
                            $reestr['type'] = trim(preg_replace("/\&.*$/", "", preg_replace("/^.*>/", "", $string)));
                            $element++;
                            break;

                        case 3:
                            $reestr['dealer'] = trim(preg_replace(
                                "/\&.*$/",
                                "",
                                preg_replace("/^.*\">/", "", $string)
                            ));
                            $element++;
                            break;

                        case 4:
                            $reestr['costumer'] = trim(preg_replace(
                                "/<.*$/",
                                "",
                                preg_replace("/^.*\">/", "", $string)
                            ));
                            $element++;
                            break;

                        case 5:
                            $reestr['state'] = trim(preg_replace("/\&.*$/", "", preg_replace("/^.*>/", "", $string)));
                            $element++;
                            break;

                        case 6:
                            $reestr['paySystem'] = trim(preg_replace(
                                "/\&.*$/",
                                "",
                                preg_replace("/^.*>/", "", $string)
                            ));
                            $element++;
                            break;

                        case 7:
                            $reestr['product'] = trim(preg_replace("/\&.*$/", "", preg_replace("/^.*>/", "", $string)));
                            $element++;
                            break;

                        case 8:
                            $reestr['creationDate'] = trim(preg_replace(
                                "/\&.*$/",
                                "",
                                preg_replace("/^.*>/", "", $string)
                            ));
                            $element++;
                            break;

                        case 9:
                            $reestr['contracts'] = trim(preg_replace(
                                "/\&.*$/",
                                "",
                                preg_replace("/^.*>/", "", $string)
                            ));
                            $element++;
                            break;

                        case 10:
                            $reestr['numbers'] = trim(preg_replace("/\&.*$/", "", preg_replace("/^.*>/", "", $string)));
                            $element++;
                            break;

                        case 11:
                            $reestr['archive'] = trim(preg_replace(
                                "/\&.*$/",
                                "",
                                preg_replace("/^.*\">/", "", $string)
                            ));
                            $element++;
                            break;

                        case 12:
                            $reestr['safe'] = trim(preg_replace("/\&.*$/", "", preg_replace("/^.*>/", "", $string)));
                            $element++;
                            break;

                        default:
                            // TODO: нужна обработка события, если в таблицу добавили новые колонки
                            break;
                    }
                }
            }
        }
        // разворачиваем общий массив
//        $reverseReestrsList = array_reverse($reestrsList);

        $response = [];
        // пробегаемся по списку реестров
        // если количество контрактов в реестре меньше 30,
        // то возвращаем этот реестр
        // если таких реестров не будет найдено, возвращается пустой массив

        foreach ($reestrsList as $reestr) {
            if (
                $reestr['state'] == "новый"
                && $reestr['contracts'] < Config::get('services.dol_reestr.contractsInReestr')
            ) {
                $response = $reestr;
                break;
            }
        }

        return response()->json($response);
    }

    /**
     * Добавление контракта в реестр
     * @param TicketInterface $ticket
     * @param DOLReestrAddContractRequest $request
     * @return JsonResponse
     */
    public function contractAdd(TicketInterface $ticket, DOLReestrAddContractRequest $request)
    {
        $params = $request->all();

        $typeIptvArr = [
            "Дозаказ оборудования",
            "ТВ. Замена приставки техником",
            "TVE. Замена приставки техником",
            "Заказ подключения/Дозаказ оборудования",
            "Конвергенция абонента"
        ];

        $responseTicketInfo = $ticket->getTicketInfo($params['ticket']);
        $ticketInfo = json_decode($responseTicketInfo);

        if (!isset($ticketInfo->original->data)) {
            echo "\n*************** ERROR GET DATA ***************\n";
            return response()->json([
                'message' => 'Нет данных от ВК',
                'detail' => 'При обращении к данным по заявке через REST API ВК не получили данные',
                'request_uri' => '$ticket->getTicketInfo',
            ], 404);
        }

        $params['billType'] = (in_array($ticketInfo->original->data->ticket_type->name, $typeIptvArr))
            ? Config::get('services.dol_reestr.billTypeIpTV')
            : Config::get('services.dol_reestr.billTypeInternet');
        $params['fio'] = iconv('UTF-8', 'CP1251', $ticketInfo->original->data->bill_name);
        $tmpArr = explode(" ", $ticketInfo->original->data->date_status);
        $dateArr = explode("-", $tmpArr[0]);
        $params['day'] = ltrim($dateArr[2], "0");
        $params['month'] = ltrim($dateArr[1], "0");
        $params['year'] = $dateArr[0];

        if ($ticketInfo->original->data->login == null) {
            return response()->json([
                'message' => 'Отсутствует логин абонента, возможно он переехал',
                'detail' => 'Отсутствует логин абонента, возможно он переехал',
                'request_uri' => '$ticket->getTicketInfo',
            ], 404);
        }

        if (!isset($ticketInfo->original->data)) {
            echo "\n*************** ERROR GET DATA ***************\n";
            return response()->json([
                'message' => 'Нет данных от ВК',
                'detail' => 'При обращении к данным по заявке через REST API ВК не получили данные',
                'request_uri' => '$ticket->getTicketContract',
            ], 404);
        }

        $responseContract = $ticket->getTicketContract($ticketInfo->original->data->login);

        if ($responseContract->status() != 200) {
            echo "\n*************** ERROR GET CONTRACT ***************\n";
            return response()->json([
                'message' => 'Не найден контракт абонента',
                'detail' => 'Не найден контракт абонента',
                'request_uri' => '$ticket->getTicketContract',
            ], 404);
        }

        $dataContract = json_decode($responseContract->content());

        $params['contract'] = $dataContract->original->contract;
        $url = Config::get('services.dol_reestr.view');
        $url = preg_replace('/{reestrnumber}/', $params['reestrnumber'], $url);
        $params['url'] = $url;
        $params['method'] = 'GET';

        $response = DOLReestrHttpRequestService::curlRequest($params);

        if (preg_match("/<title>Ошибка/", $response['body'])) {
            return response()->json([
                'message' => 'При начальной загрузке формы возвратилась страница с ошибкой!',
                'detail' => $response['body'],
                'request_uri' => $response['info']['url'],
            ], $response['info']['http_code']);
        }

        // ищем точку продаж
        $bodyArr = explode("\r\n", $response['body']);
        $cPointDealerFlag = false;

        foreach ($bodyArr as $string) {
            $string = mb_convert_encoding($string, "UTF-8", "CP1251");
            if ($cPointDealerFlag) {
                if (preg_match("/option value/", $string) && !preg_match("/-1/", $string)) {
                    preg_match("/\d+/", $string, $matches);
                    $params['cPointDealer'] = $matches[0];
                    break;
                }
            }

            if (preg_match("/Выберите точку обслуживания/", $string)) {
                $cPointDealerFlag = true;
            }
        }

        if ($response['info']['http_code'] == 200) {
            $validators1 = $this->getValidators($response['body']);

            $params = array_merge(
                $params,
                [
                    'postData' => [
                        '__EVENTTARGET' => '_ctl0$_ctl0$sDealerAndPoint$ddPoints',
                        '__EVENTARGUMENT' => '',
                        '__LASTFOCUS' => '',
                        '__VIEWSTATE' => $validators1['view'],
                        '__VIEWSTATEGENERATOR' => $validators1['generator'],
                        '__EVENTVALIDATION' => $validators1['event'],
                        '_ctl0:_ctl0:tbeNumber' => $params['contract'],
                        '_ctl0:_ctl0:tbeName' => $params['fio'],
                        '_ctl0:_ctl0:DateReg:ddDay' => $params['day'],
                        '_ctl0:_ctl0:DateReg:ddMonth' => $params['month'],
                        '_ctl0:_ctl0:DateReg:ddYear' => $params['year'],
                        '_ctl0:_ctl0:sDealerAndPoint:ddPoints' => $params['cPointDealer'],
                        '_ctl0:_ctl0:sDealerAndPoint:iscodepoint' => '',
                        '_ctl0:_ctl0:tbeDescription' => '',
                        '_ctl0:_ctl0:hPaySystem' => '3',
                        '_ctl0:_ctl0:hDolId' => '',
                        '_ctl0:_ctl0:hBan' => '',
                        '_ctl0:_ctl0:hiddenContractTypeID' => '4',
                        '_ctl0:_ctl0:PhonesList:tbNumber' => '',
                        '_ctl0:_ctl0:PhonesList:tbNumber2' => '',
                        '_ctl0:_ctl0:PhonesList:BillPlan:ddCellNet' => '-1',
                        '_ctl0:_ctl0:PhonesList:BillPlan:ddBillPlan' => '-1',
                        'cophlicommarg' => '',
                        '_ctl0:_ctl0:hSourceID' => '',
                        '_ctl0:_ctl0:hSourceItemStatus' => '',
                        '_ctl0:_ctl0:hSourceItemStatusName' => ''
                    ]
                ]
            );

            $params['method'] = 'POST';
            $response = DOLReestrHttpRequestService::curlRequest($params);

            if (preg_match("/<title>Ошибка/", $response['body'])) {
                return response()->json([
                    'message' => 'При добавлении первого блока информации в реестр возвратилась страница с ошибкой!',
                    'detail' => $response['body'],
                    'request_uri' => $response['info']['url'],
                ], $response['info']['http_code']);
            }

            if ($response['info']['http_code'] == 200) {
                $validators2 = $this->getValidators($response['body']);

                // получаем точку продаж указанную при загрузке сертификата
                $dolcert = DOLCert::where('login', $params['login'])->get();
                $certInfo = $dolcert->values()->all();

                // ещё добавляем информацию в форму
                $params = array_merge(
                    $params,
                    [
                        'postData' => [
                            '__EVENTTARGET' => '_ctl0$_ctl0$PhonesList$BillPlan$ddCellNet',
                            '__EVENTARGUMENT' => '',
                            '__LASTFOCUS' => '',
                            '__VIEWSTATE' => $validators2['view'],
                            '__VIEWSTATEGENERATOR' => $validators2['generator'],
                            '__EVENTVALIDATION' => $validators2['event'],
                            '_ctl0:_ctl0:tbeNumber' => $params['contract'],
                            '_ctl0:_ctl0:tbeName' => $params['fio'],
                            '_ctl0:_ctl0:DateReg:ddDay' => $params['day'],
                            '_ctl0:_ctl0:DateReg:ddMonth' => $params['month'],
                            '_ctl0:_ctl0:DateReg:ddYear' => $params['year'],
                            '_ctl0:_ctl0:sDealerAndPoint:ddPoints' => $params['cPointDealer'],
                            '_ctl0:_ctl0:sDealerAndPoint:iscodepoint' => $certInfo[0]->sellpoint,
                            '_ctl0:_ctl0:tbeDescription' => '',
                            '_ctl0:_ctl0:hPaySystem' => '3',
                            '_ctl0:_ctl0:hDolId' => '',
                            '_ctl0:_ctl0:hBan' => '',
                            '_ctl0:_ctl0:hiddenContractTypeID' => '4',
                            '_ctl0:_ctl0:hMarketCode' => '',
                            '_ctl0:_ctl0:PhonesList:tbNumber' => $params['ticket'],
                            '_ctl0:_ctl0:PhonesList:tbNumber2' => '',
                            '_ctl0:_ctl0:PhonesList:BillPlan:ddCellNet' => '4',
                            '_ctl0:_ctl0:PhonesList:BillPlan:ddBillPlan' => '-1',
                            'cophlicommarg' => '',
                            '_ctl0:_ctl0:hSourceID' => '',
                            '_ctl0:_ctl0:hSourceItemStatus' => '',
                            '_ctl0:_ctl0:hSourceItemStatusName' => ''
                        ]
                    ]
                );

                $response = DOLReestrHttpRequestService::curlRequest($params);

                if (preg_match("/<title>Ошибка/", $response['body'])) {
                    return response()->json([
                        'message' => 'При добавлении второго блока информации в реестр возвратилась страница с ошибкой!',
                        'detail' => $response['body'],
                        'request_uri' => $response['info']['url'],
                    ], $response['info']['http_code']);
                }

                if ($response['info']['http_code'] == 200) {
                    $validators3 = $this->getValidators($response['body']);

                    // финальное добавление информации в форму
                    $params = array_merge($params,
                        [
                            'postData' => [
                                '__EVENTTARGET' => '',
                                '__EVENTARGUMENT' => '',
                                '__LASTFOCUS' => '',
                                '__VIEWSTATE' => $validators3['view'],
                                '__VIEWSTATEGENERATOR' => $validators3['generator'],
                                '__EVENTVALIDATION' => $validators3['event'],
                                '_ctl0:_ctl0:tbeNumber' => $params['contract'],
                                '_ctl0:_ctl0:tbeName' => $params['fio'],
                                '_ctl0:_ctl0:DateReg:ddDay' => $params['day'],
                                '_ctl0:_ctl0:DateReg:ddMonth' => $params['month'],
                                '_ctl0:_ctl0:DateReg:ddYear' => $params['year'],
                                '_ctl0:_ctl0:sDealerAndPoint:ddPoints' => $params['cPointDealer'],
                                '_ctl0:_ctl0:sDealerAndPoint:iscodepoint' => $certInfo[0]->sellpoint,
                                '_ctl0:_ctl0:tbeDescription' => '',
                                '_ctl0:_ctl0:hPaySystem' => '3',
                                '_ctl0:_ctl0:hDolId' => '',
                                '_ctl0:_ctl0:hBan' => '',
                                '_ctl0:_ctl0:hiddenContractTypeID' => '4',
                                '_ctl0:_ctl0:hMarketCode' => '',
                                '_ctl0:_ctl0:PhonesList:tbNumber' => $params['ticket'],
                                '_ctl0:_ctl0:PhonesList:tbNumber2' => '',
                                '_ctl0:_ctl0:PhonesList:BillPlan:ddCellNet' => '4',
                                '_ctl0:_ctl0:PhonesList:BillPlan:ddBillPlan' => $params['billType'],
                                'cophlicommarg' => '',
                                '_ctl0:_ctl0:bSaveAll' => '%D1%EE%F5%F0%E0%ED%E8%F2%FC', // 'Сохранить'
                                '_ctl0:_ctl0:hSourceID' => '',
                                '_ctl0:_ctl0:hSourceItemStatus' => '',
                                '_ctl0:_ctl0:hSourceItemStatusName' => ''
                            ]
                        ]
                    );

                    $response = DOLReestrHttpRequestService::curlRequest($params);
                    $body = mb_convert_encoding($response['body'], "UTF-8", "CP1251");

                    if (preg_match("/<title>Ошибка/", $body)) {
                        return response()->json([
                            'message' => 'При сохранении всей информации в реестр возвратилась страница с ошибкой!',
                            'detail' => $response['body'],
                            'request_uri' => $response['info']['url'],
                        ], $response['info']['http_code']);
                    } elseif (preg_match('/Нельзя добавить договор в реестр/', $body)) {
                        return response()->json([
                            'message' => 'Нельзя добавить договор в реестр. Договор с таким кодом уже есть в реестре.',
                            'detail' => 'Нельзя добавить договор в реестр. Договор с таким кодом уже есть в реестре.',
                            'request_uri' => $response['info']['url'],
                        ], 400);
                    } else {
                        return response()->json([
                            'result' => 'success',
                            'message' => 'Контракт добавлен'
                        ]);
                    }
                }
            } else {
                return response()->json([
                    'message' => 'При добавлении первого блока информации в реестр возвратилась страница с ошибкой!',
                    'detail' => $response['body'],
                    'request_uri' => $response['info']['url'],
                ], $response['info']['http_code']);
            }
        } else {
            return response()->json([
                'message' => 'При открытии реестра произошла ошибка',
                'detail' => $response['body'],
                'request_uri' => $response['info']['url'],
            ], $response['info']['http_code']);
        }
    }

    public function completed(Request $request)
    {
        // TODO: Implement completed() method.
    }
}
