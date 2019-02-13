<?php
namespace AppBundle\Services;

use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Swift_Mailer;

class MOZ
{
    public $moz_url;
    public $moz_login;
    public $moz_password;
    public $io;
    private $container;
    private $mailer;

    public function __construct(ContainerInterface $container, Swift_Mailer $mailer, $moz_url, $moz_login, $moz_password)
    {

        $this->moz_url = $moz_url;
        $this->moz_login = $moz_login;
        $this->moz_password = $moz_password;
        $this->container = $container;
    }

    /**
     * Оформляем заявку в МОЗе
     * @param string $report_id id отчета (например 22.2)
     * @param string $format формат файла отчета
     * @param string $calc_start_date расчетный период с
     * @param string $calc_end_date расчетный период по
     * @param string $start_date период подключения с
     * @param string $end_date период подключения по
     * @return
     */
	public function MOZ_order($report_id, $format, $calc_start_date=false, $calc_end_date=false, $start_date=false, $end_date=false)
	{
		date_default_timezone_set('UTC');
//		date_default_timezone_set('Europe/Moscow');
		require ("MOZ_postdata.php");
		$rep = str_replace(".", "_", $report_id);
        $result = $this->MOZ_html("store_request.php", $postdata[$rep]);
//        $result = iconv("windows-1251", 'utf-8', $result);
		$crawler = new Crawler($result);
		$text = $crawler->filter('title')->text();
		preg_match_all("/[0-9]+/", $text, $matches);
		$number = $matches[0][0];

		return $number;
	}

    /**
     * Новая версия заказа отчета
     * @param $data array массив данных для заказа отчета
     * @return mixed
     */
	public function order($data)
    {
        date_default_timezone_set('UTC');
        require ("MOZ_postdata.php");
        $rep = str_replace(".", "_", $data['report_id']);
        $result = $this->MOZ_html("store_request.php", $postdata[$rep]);
        $result = iconv("windows-1251", 'utf-8', $result);
        $crawler = new Crawler($result);
        preg_match_all("/[0-9]+/", $crawler->filter('title')->text(), $matches);
        $number = $matches[0][0];

        return $number;
    }

	/**
	 * Проверяем статус заявки
	 * @param integer $num номер заявки
	 * @return boolean true/false
	 */
	public function MOZ_check_status($num) {
		$postdata = array(
			'sorting_field' => "request_id",
			'sorting_direction' => "desc",
			'page' => "0",
			'expand-filters' => "1",
			'filter_status' => "0",
			'filter_correctness' => "*",
			'filter_start_date' => "01.05.2016",
			'filter_end_date' => "",
			'filter_request_id' => "$num",
			'filter_report_id' => "*",
			'filter_report_mask' => "",
			'filter_user_id' => "*",
			'filter_user_mask' => "",
			'filter_repeat' => "*"
		);
        $url = "requests_table.php";
        $result = $this->MOZ_html($url, $postdata);
//        $result = iconv("windows-1251", "utf-8", $result);
		$crawler = new Crawler($result);
		$text = trim($crawler->filterXPath(".//*[@id='requests_table_scrollable']/tbody/tr/td[3]")->text());
		$status = ($text == "Выполнена успешно") ? true : false;
		return $status;
	}

    /**
     * Проверяем дату актуальности копии INAC и размер файла
     * @param $num integer номер заявки
     * @return array ошибка или нет ошибки
     */
	public function MOZ_check_size($num)
    {
        $data = [];
        $result = $this->MOZ_html("report_form.php?request_id=$num");

        // Проверяем размер файла
        $crawler = new Crawler($result);
        $text = trim($crawler->filterXPath(".//*[@id='buttons_div']/div/label")->text());
        echo $text . "\n";
        preg_match('/^0,/', $text, $result);

        if (count($result) > 0) {
            $data['error'] = true;
            $data['msg'] = "Размер отчета равен 0";
        } else {
            $data['error'] = false;
        }

        return $data;
    }

	public function MOZ_order_download($order, $report_id, $format, $path) {
	    ini_set("memory_limit","2G");
		$url = 	$this->moz_url . "/report_form.php?request_id=$order";
        $result = $this->MOZ_html("report_form.php?request_id=$order");
	    // Проверяем отчет в архиве или нет
		$crawler = new Crawler($result);
		$text = trim($crawler->filterXPath(".//*[@id='buttons_div']/div/label")->text());
	    preg_match('/\((.+)\)/', $text, $zip);

	    if(!$zip) {
	    	return false;
	    }

	    if($zip[1] == "не zip") {
			$filename = $order . "-$report_id.xlsx";
	    } elseif($zip[1] == "zip") {
			if($format == "CSV_NO_HEADER" || $format == "CSV" || $format == "FAST_CSV" || $format == "Text_TAB") {
			    $type_f = "txt";
            }
			if($format == "XLS") {
			    $type_f = "xls";
            }
			if($format == "XLSX") {
			    $type_f = "tar";
            }
			$filename = $order . "-$report_id.$type_f.gz";
			$filename1 = $order . "-$report_id.$type_f.gz";
		}
		// скачиваем файл
        /** @var string $filename */
        $dest_file = fopen($path . "\\" . $filename, "w+");
        $result = $this->MOZ_html("result_viewer.php?request_id=$order&mode=store", false, $dest_file);
	    fflush($dest_file);
	    fclose($dest_file);
	    // если отчет в архиве распаковываем
	    if($zip[1] == "zip") {
	    	if($format == "CSV_NO_HEADER" || $format == "FAST_CSV" || $format == "Text_TAB" || $format == "CSV") {
	    		$filename = $path . "\\" . $order."-".$report_id.".".$type_f;
	    		if (preg_match("/Windows/", $_SERVER['OS'])) {
	    		    system("7z x -aoa $path\\$filename1 -o$path");
                } else {
                    system("gzip -d -f $filename");
                }
	    		$a = file_get_contents($filename);
	    		$ff = preg_replace("/\n;/", ";", $a);
	    		file_put_contents($filename, $ff);
	    	}
	    	if($format == "XLSX") {
	    	    system("tar -xvf $filename -C $path");
            }
	    }
	    return $filename;
	}

	public function MOZ_html($url, $postdata = false, $file = false)
    {
        $headers = array
        (
            'Accept: text/html,application/xhtml+xml,application/xml;q=0.9,*/*;q=0.8',
            'Accept-Language: ru-RU,ru;q=0.8,en-US;q=0.5,en;q=0.3',
            'Accept-Charset: windows-1251, utf-8;q=0.7,*;q=0.7'
        );
        $ch = curl_init();
        curl_setopt ($ch, CURLOPT_USERAGENT, "Mozilla/5.0 (Windows NT 6.1; WOW64; rv:44.0) Gecko/20100101 Firefox/44.0");
        curl_setopt($ch, CURLOPT_HTTPHEADER,$headers);
        curl_setopt($ch, CURLOPT_URL, $this->moz_url . "/$url");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, FALSE);
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, 1);
        curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_NTLM);
        curl_setopt($ch, CURLOPT_USERPWD, "$this->moz_login:$this->moz_password");

        if ($postdata) {
            curl_setopt($ch, CURLOPT_POST, 1);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $postdata);
        }

        if ($file) {
            curl_setopt($ch, CURLOPT_POST, 0);
            curl_setopt($ch, CURLOPT_FILE, $file);
        }

        $result = curl_exec($ch);

        if ($result === false) {
            echo "Ошибка curl: " . curl_error($ch);
        }

        if ($result !== true) {
            $crawler = new Crawler($result);

            try {
                $text = trim($crawler->filterXPath(".//*[@id='content']/div/fieldset/h2")->text());
                if (strstr($text, "401 - Unauthorized")) {
                    echo "Ошибка авторизации в МОЗ\n";
                    $Subscription = $this->container->get('subscription');
                    $data['subject'] = "Ошибка авторизации в МОЗ";
                    $data['email'] = "DKostin@nvbs.ru";
                    $data['body'] = "kpi2test.arbuse.ru ошибка авторизации МОЗ";
                    $data['from'] = "KPI NVBS";
//                    $result = $Subscription->sendMail($data);
                    exit;
                }
            } catch (\Exception $e) {
                if ($e->getMessage() ) {

                }
            }
        }

        return $result;
    }
}
