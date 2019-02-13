<?php
/**
 * Получить тикеты на текущую дату
 */
function getTickets () {
	global $date, $period, $yesterday;

	echo "$date: Получаем список заявок\n";
	$tickets = file_get_contents("domain/vk_listticketsfordate.php?datec={$date}&period={$period}");

	// сохраняем тикеты в файл
	file_put_contents("./tickets/tickets_{$period}_{$date}.json", $tickets);
}

/**
 * Получить не подключенные тикеты
 * @return mixed
 */
function getNotConnectedTickets() {
	global $date, $dbkpi;

	echo date("d.m.Y H:i:s", time()) . ": Получаем неподключенные заявки\n";
	$yesterday = date("Y-m-d", strtotime("-1 day", strtotime($date)));

	$query = "
		select
		  *
		from
		  Moz_22_34
		where
		  (assigned_to BETWEEN '$yesterday 00:00:00' AND '$yesterday 23:59:59')
		  AND result = ''
		  AND (status_ticket_end_period != 'Спутник IPTV' && status_ticket_end_period != 'Ждем активации' && status_ticket_end_period != 'Спутник TV')
		  AND (status_end_period != 'Спутник IPTV' && status_end_period != 'Спутник TV') 
		  AND district NOT IN ('Неподключеные дома', 'Тестовая сеть', 'Неподключенные дома', 'Удаленные дома')
		  AND fio NOT IN ('тест', 'Тест', 'Тестовый Тест Тестович', 'test', 'test test test', 'тест тест тест')
		group by ticket
	";

    $result = $dbkpi->query($query);

    return $result;
}

function getTicketsWithComments() {
    global $dbh, $date;

    echo date("d.m.Y H:i:s", time()) . ": Получаем список заявок с комментами\n";

    $query = "
    	SELECT
		  ticket_comment.ticket,
		  DATE_FORMAT(ticket_comment.created_at, '%Y-%m-%d') as created_at,
		  ticket_comment.tmpl_id,
		  c2.text as comment,
		  c1.text
		FROM close_tempate_guide c1
		  INNER JOIN close_tempate_guide c2
		    ON c2.parent_id = c1.id
		  INNER JOIN ticket_comment
		    ON ticket_comment.tmpl_id = c2.id
		WHERE ticket_comment.created_at between DATE_SUB('{$date}', INTERVAL 7 DAY) AND DATE_ADD('{$date}', INTERVAL 7 DAY)
		  AND ticket_comment.tmpl_id IS NOT NULL
    ";

    $result = $dbh->query($query);
    $ticketsWithComments = [];
    while ($row = $result->fetch_assoc()) {
        $ticketsWithComments[$row['ticket']] = array(
            'group' => $row['text'],
            'comment' => $row['comment']
        );
    }

    return $ticketsWithComments;
}

/**
 * Устанавливаем комментарий на каждую заявку
 * Если заявка есть в списке всех заявок на указанную дату - ставим шаблонный комментарий если он есть, если нет, то пусто
 * Если заявки нет в списке всех заявок - ставим комментарий "Удаление из графика"
 *
 * @param $rows array выборка всех неподключенных заявок
 * @param $ticketsWithComments array список заявок с комментами для выставления коммента неподключенной заявке
 * @return array список неподключенных с комментариями
 */
function setCommentOnTicket($rows, $ticketsWithComments) {
	global $date, $yesterday;

	echo date("d.m.Y H:i:s", time()) . ": Проставляем заявкам комменты\n";

	// заявки на будущее
	$json = file_get_contents("./tickets/tickets_tomorrow_{$yesterday}.json");
	$tickets = json_decode($json, true);

	// перебираем массив и выставляем ключом номер тикета
	$ticketsTomorrow = [];
	foreach ($tickets as $key => $ticket) {
		$ticketsTomorrow[$ticket['OrgTicketCode']] = $ticket;
	}

	// заявки на дату
	$json = file_get_contents("./tickets/tickets_date_{$yesterday}.json");
	$tickets = json_decode($json, true);

	// перебираем массив и выставляем ключом номер тикета
	$ticketsOnDate = [];
	foreach ($tickets as $key => $ticket) {
		// если заявка не имеет назначений в будущем или имеет, но и есть шаблонный коммент
		// то сохраняем заявку в массиве заявок на начало дня.
		if (!array_key_exists($ticket['OrgTicketCode'], $ticketsTomorrow)
			|| (array_key_exists($ticket['OrgTicketCode'], $ticketsTomorrow) && array_key_exists($ticket['OrgTicketCode'], $ticketsWithComments)))
		{
			$ticketsOnDate[$ticket['OrgTicketCode']] = $ticket;
		}
	}

	$ticketsNotConnected = [];
	foreach ($rows as $key => $ticket) {
		if ($ticket['district'] == "Тестовая сеть") continue;
		$dateAssignedTo = strtotime($ticket['assigned_to']);
		$dateStatusTicketEndPeriod = strtotime($ticket['date_status_ticket_end_period']);
		$statusTicketEndPeriod = $ticket['status_ticket_end_period'];
		$monday = date("Y-m-d", strtotime("last monday"));
		$group = "";
		$comment = "";
		if (!array_key_exists($ticket['ticket'], $ticketsOnDate)) {
			$group = "ошибка продавца";
			$comment = "Ошибка продавца: Удаление заявки из графика подключений до даты назначения. ";
		} else {
			// ищем коммент из АМУРа по заявке
            if (array_key_exists($ticket['ticket'], $ticketsWithComments)) {
                $group = $ticketsWithComments[$ticket['ticket']]['group'];
                $comment = $ticketsWithComments[$ticket['ticket']]['comment'];
			} else {
				if ($dateAssignedTo > $dateStatusTicketEndPeriod && $statusTicketEndPeriod != "Назначено в график") {
					$group = "ошибка продавца";
					$comment = "Ошибка продавца: Удаление заявки из графика подключений до даты назначения. ";
				}
			}
		}


		$ticket['comment_group'] = $group;
		$ticket['comment_text'] = $comment;
		$ticketsNotConnected[$key] = $ticket;
	}
	return $ticketsNotConnected;
}

/**
 * Сохраняем тикеты с комментами в таблицу
 * @param $insert array массив тикетов
 */
function insertInDb($insert, $assignedTo) {
	global $dbkpi;

	$query = "
		INSERT INTO
			amur_not_connected (
				region,
				branch,
				city,
				district,
				ticket,
				ctn,
				assigned_to,
				channel_sales,
				code_partner,
				name_partner,
				code_officer,
				status_ticket_end_period,
				comment_group,
				comment_text
			  ) VALUES
	";

	$query .= implode(",", $insert);

	if (!$dbkpi->query($query)) {
		echo $dbkpi->error . "\n";
		exit;
	}
}

function createFileXlsx($tickets, $period = false, $periodValue = false) {
	global $date, $objPHPExcel, $yesterday, $dbkpi;

	echo date("d.m.Y H:i:s", time()) . ": Сохраняем отчет за $period\n";
	// устанавливаем стиль границы ячеек шапка таблицы
	$styleBorderHeader = array(
		'borders' => array(
			'allborders' => array(
				'style' => PHPExcel_Style_Border::BORDER_THIN,
				'color' => array('argb' => '000'),
			)
		),
		'fill' => array(
			'type' => PHPExcel_Style_Fill::FILL_SOLID,
			'color' => array('argb' => 'ffffe598')
		)
	);
	// устанавливаем стиль границы ячеек
	$styleBorderCell = array(
		'borders' => array(
			'allborders' => array(
				'style' => PHPExcel_Style_Border::BORDER_THIN,
				'color' => array('argb' => '000'),
			),
		),
	);

	switch ($period) {
		case "week":
			$titleA = "Неделя";
			$valueA = $periodValue;
			break;
		case "day":
			$titleA = "День";
			$valueA = $yesterday;
			echo "Удаляем данные из amur_not_connected на $yesterday\n";
			$query = "DELETE FROM amur_not_connected WHERE DATE(assigned_to) ='$yesterday'";
			$dbkpi->query($query);
			break;
	}

	$jsonRegionVk = file_get_contents("../regionVk.json");
	$regionVk = json_decode($jsonRegionVk);

	foreach ($regionVk as $key => $region) {
		echo $region . "\n";
		$objPHPExcel = new PHPExcel();
		$sheetConnect = $objPHPExcel->getActiveSheet();
		$sheetConnect->setTitle($period);
		$sheetConnect->getStyle('A1:Q1')->getAlignment()->setVertical(PHPExcel_Style_Alignment::VERTICAL_CENTER);
		$sheetConnect->getStyle('A1:Q1')->applyFromArray($styleBorderHeader);

		$sheetConnect->setCellValue("A1", $titleA);
		$sheetConnect->getColumnDimension('A')->setAutoSize(true);

		$sheetConnect->setCellValue("B1", "Регион");
		$sheetConnect->getColumnDimension('B')->setAutoSize(true);

		$sheetConnect->setCellValue("C1", "Филиал");
		$sheetConnect->getColumnDimension('C')->setAutoSize(true);

		$sheetConnect->setCellValue("D1", "Город");
		$sheetConnect->getColumnDimension('D')->setAutoSize(true);

		$sheetConnect->setCellValue("E1", "Район");
		$sheetConnect->getColumnDimension('E')->setAutoSize(true);

		$sheetConnect->setCellValue("F1", "№ заявки");
		$sheetConnect->getColumnDimension('F')->setAutoSize(true);

		$sheetConnect->setCellValue("G1", "CTN");
		$sheetConnect->getColumnDimension('G')->setAutoSize(true);

		$sheetConnect->setCellValue("H1", "Дата, на которую была назначена заявка");
		$sheetConnect->getColumnDimension('H')->setAutoSize(true);

		$sheetConnect->setCellValue("I1", "Канал продаж");
		$sheetConnect->getColumnDimension('I')->setAutoSize(true);

		$sheetConnect->setCellValue("J1", "Код сотрудника ФИО");
		$sheetConnect->getColumnDimension('J')->setAutoSize(true);

		$sheetConnect->setCellValue("K1", "Статус заявки на конец даты, на которую была назначена заявка");
		$sheetConnect->getColumnDimension('K')->setAutoSize(true);

		$sheetConnect->setCellValue("L1", "Мнение НВБС");
		$sheetConnect->getColumnDimension('L')->setAutoSize(true);

		$sheetConnect->setCellValue("M1", "Мнение продаж");
		$sheetConnect->getColumnDimension('M')->setAutoSize(true);

		$sheetConnect->setCellValue("N1", "Причина не выполнения");
		$sheetConnect->getColumnDimension('N')->setAutoSize(true);

		$sheetConnect->setCellValue("O1", "Примечание НВБС");
		$sheetConnect->getColumnDimension('O')->setWidth(100);

		$sheetConnect->setCellValue("P1", "Примечание продаж");
		$sheetConnect->getColumnDimension('P')->setAutoSize(true);

		$sheetConnect->setCellValue("Q1", "Общее примечание");
		$sheetConnect->getColumnDimension('Q')->setAutoSize(true);

		$sheetConnect->getStyle('A1:Q1')->getAlignment()->setHorizontal(PHPExcel_Style_Alignment::HORIZONTAL_CENTER);

		$row    = 2;
		$insert = [];

		foreach ($tickets as $key => $ticket) {
			if ($ticket['region'] != $region ) {
				continue;
			}
			$sheetConnect->setCellValue("A$row", $valueA);
			$sheetConnect->setCellValue("B$row", $ticket['region']);
			$sheetConnect->setCellValue("C$row", $ticket['branch']);
			$sheetConnect->setCellValue("D$row", $ticket['city']);
			$sheetConnect->setCellValue("E$row", $ticket['district']);
			$sheetConnect->setCellValue("F$row", $ticket['ticket']);
			$sheetConnect->setCellValue("G$row", $ticket['ctn']);
			$sheetConnect->setCellValue("H$row", $ticket['assigned_to']);
			$sheetConnect->setCellValue("I$row", $ticket['channel_sales']);
			$partner = $ticket['code_partner'] . " / " . $ticket['name_partner'] . " / " . $ticket['code_officer'];
			$sheetConnect->setCellValue("J$row", $partner);
			$sheetConnect->setCellValue("K$row", $ticket['status_ticket_end_period']);
			$sheetConnect->setCellValue("L$row", $ticket['comment_group']);
			$sheetConnect->setCellValue("M$row", " ");
			$sheetConnect->setCellValue("N$row", " ");
			$sheetConnect->setCellValue("O$row", $ticket['comment_text']);
			$sheetConnect->setCellValue("P$row", " ");
			$sheetConnect->setCellValue("Q$row", " ");
			$row++;
			$insert[] = "(
				'{$ticket['region']}',
				'{$ticket['branch']}',
				'{$ticket['city']}',
				'{$ticket['district']}',
				'{$ticket['ticket']}',
				'{$ticket['ctn']}',
				'{$ticket['assigned_to']}',
				'{$ticket['channel_sales']}',
				'{$ticket['code_partner']}',
				'{$ticket['name_partner']}',
				'{$ticket['code_partner']}',
				'{$ticket['status_ticket_end_period']}',
				'{$ticket['comment_group']}',
				'{$ticket['comment_text']}'
			)";
		}

		if ($period !== "week") {
			echo 'Запись в БД\n';
			$assignedTo = $ticket['assigned_to'];
			if ($insert) {
				insertInDb($insert, $assignedTo);
			}
		}

		$row--;
		$sheetConnect->getStyle("A2:Q$row")->applyFromArray($styleBorderCell);

		if ($period == "week") {
			if (strlen($periodValue) == 1) {
				$periodValue = "0" . $periodValue;
			}
			$periodWeek = "неделю";
			$filename = "./weeks/Отчет по неподключенным за {$periodValue} {$periodWeek} {$year}_{$region}.xlsx";
			echo $filename . "\n";
		} elseif ($period == "day") {
			$filename = "./days/Отчет по неподключенным за {$yesterday}_{$region}.xlsx";
		}

        // добавляем лист со справочной информацией
        $objReader = PHPExcel_IOFactory::createReader('Excel2007');
        $filenameHelp = "addSheet.xlsx";
        $fileHelp = $objReader->load($filenameHelp);
        $sheetHelp = $fileHelp->getActiveSheet();
        $objPHPExcel->addExternalSheet($sheetHelp);
		$objWriter = PHPExcel_IOFactory::createWriter($objPHPExcel, "Excel2007");
		$objWriter->save($filename);
		$objPHPExcel->disconnectWorksheets();
	}
}

function reportWeek($argv) {
	global $dbkpi, $Date;

	$year = date("Y", time());
	$currentWeek = date("W", time());

	if ($currentWeek == 1) {
		$year--;
		$week = $Date->getLastWeekYear($year);
	} else {
		$week = (isset($argv[2])) ? $argv[2] : (int)date("W", strtotime("-1 week"));
		$year = (isset($argv[3])) ? $argv[3] : $year;
	}

	$firstLastDayWeek = $Date->getFirstLastDayWeek($year, $week, true);

	$query = "
		SELECT
			*
		FROM
			amur_not_connected
		WHERE
			DATE(assigned_to) BETWEEN '{$firstLastDayWeek['firstDay']}' AND '{$firstLastDayWeek['lastDay']}'
	";

	$result = $dbkpi->query($query);
	$tickets = [];

	while ($row = $result->fetch_assoc()) {
		$tickets[] = $row;
	}

	createFileXlsx($tickets, "week", $week);
}

if (isset($_SERVER['PWD']) && strstr($_SERVER['PWD'], 'domain')) {
	require_once "../../../services/PHPExcel/PHPExcel.php";
	require_once "../../db_include.php";
	require_once "../../../services/Date.php";
} else {
    require_once "../../db_include.php";
	require_once "../../services/PHPExcel/PHPExcel.php";
	require_once "../../services/Date.php";
}

$objPHPExcel = new PHPExcel();
$Date = new Date();

if (!isset($argv[1])) {
	echo "Необходимо указать операцию (get-tickects / report)\n";
	exit;
}

$date      = (isset($argv[3])) ? $argv[3] : date('Y-m-d', time());
$yesterday = date("Y-m-d", strtotime("-1 day", strtotime($date)));
$period    = (isset($argv[2])) ? $argv[2] : 'tomorrow';

switch ($argv[1]) {
	case "get-tickets":
		getTickets();
		break;
	case "report":
		$ticketsNotConnected = getNotConnectedTickets();
		$ticketsWithComments = getTicketsWithComments();
		$ticketsNotConnected = setCommentOnTicket($ticketsNotConnected, $ticketsWithComments);
		createFileXlsx($ticketsNotConnected, "day");
		break;
	case "week":
		reportWeek($argv);
		break;

}
