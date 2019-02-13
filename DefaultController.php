<?php

namespace AccidentBundle\Controller;

use AppBundle\Controller\InitControllerInterface;
use Sensio\Bundle\FrameworkExtraBundle\Configuration\Method;
use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Annotation\Route;

/**
 * @property \Doctrine\Common\Persistence\ObjectManager|object em
 * @property \Doctrine\Common\Persistence\ObjectManager|object repository
 */
class DefaultController extends Controller
{
    /**
     * @Route("/accident", name="nvbs_accident_homepage")
     */
    public function indexAction($filter=false)
    {
	//echo phpinfo();exit;
        $user = $this->getUser();
        $em = $this->getDoctrine()->getManager();
        $repository = $em->getRepository('AccidentBundle:Accident');

        // проверяем наличие уведомлений
        $Notices     = $em->getRepository('AppBundle:Notices');
        $noticesReport = $this->getParameter('accident_report_name');
        $notice = $Notices->getNotice($noticesReport);

        // если текущая неделя = 1, год выбираем предыдущий
        if (date("W", time()) == 1) {
            $currentYear = date("Y", time()) - 1;
        } else {
            $currentYear = date("Y", time());
        }

        $lastWeek = $repository->findLastWeek($currentYear);
        $lastYear = $repository->findLastYear();

        // строим фильтр по годам
        $filterYear = $this->get('filteryear');
        $years = (!$filter) ? $filterYear->filterCreate($repository, $lastYear)
            : $filterYear->filterCreate($repository, $currentYear);

        // строим фильтр по неделям
        $filterWeek = $this->get('filterweek');

        $weeks = (!$filter) ? $filterWeek->filterCreate(true, false, $repository, $lastYear, $lastWeek)
            : $filterWeek->filterCreate(true, $repository, $filter->get('year'), $filter->get('week'));

        // статистика
        $stats = $this->stats($repository, false, $years, $weeks);

        // выбираем аварии с учетом фильтров
        $accident = $repository->searchAccident($lastYear, $lastWeek);

        // фильтры по регионам
        $region = $this->selectRegion();


        if (!$accident) {
            throw $this->createNotFoundException(
                'No accidents'
            );
        }

        // проверяем подписку
        $subs = $this->get('subscription');
        $result = $subs->checkSubscription($user->getEmail(), "accident");
        if ($result == true) {
            $subscribeText = "Отписаться";
        } else {
            $subscribeText = "Подписаться";
        }
        return $this->render('AccidentBundle:Default:index.html.twig', array(
            'years' => $years,
            'weeks' => $weeks,
            'stats' => $stats,
            'accident' => $accident,
            'region' => $region,
            'subscribeText' => $subscribeText,
            'viewButton' => 'overdue',
            'notice' => $notice
        ));
    }

    /**
     * @Route("/accident/search", name="accident_search")
     * @Method("POST")
     */
    public function searchAction(Request $request)
    {
        if ($request->isXmlHttpRequest()) {
            $filters = $request->request;
            $em = $this->getDoctrine()->getManager();
            $repository = $em->getRepository('AccidentBundle:Accident');

            // строим фильтр по годам
            $filterYear = $this->get('filteryear');
            $years = $filterYear->filterCreate($repository, $filters->get('year'));

            // строим фильтр по неделям
            $filterWeek = $this->get('filterweek');
            $weeks = $filterWeek->filterCreate(true, false, $repository, $filters->get('year'), $filters->get('week'));

            // статистика
            $stats = $this->stats($repository, $filters, $years, $weeks);

            // выбираем аварии с учетом фильтров
            $accident = $repository->searchFilters($filters);

            return $this->render('AccidentBundle:Default:content.html.twig', array(
                'years' => $years,
                'weeks' => $weeks,
                'stats' => $stats,
                'accident' => $accident,
                'viewButton' => $filters->get('viewButton')
                ));
        }
    }

    /*
     * Собираем статистику
     */
    private function stats($repository, $filters=false, $years=false, $weeks=false)
    {
        if (!$filters) {
            // смотрим какой год активный
            foreach ($years as $key => $value) {
                if ($value[1] == "active") {
                    $year = $value[0];
                } else {
                    continue;
                }
            }

            // смотрим какая неделя активна
            foreach ($weeks as $key => $value) {
                if ($weeks[$key]['class'] == "active") {
                    $week = $key;
                } else {
                    continue;
                }
            }
        } else {
            $year = $filters->get('year');
            $week = $filters->get('week');
        }

        $Date = $this->get('date_service');
        $d = $Date->getFirstLastDayWeek($year, $week);

        $stats = array();

        $stats['firstDay'] = $d['firstDay'];
        $stats['lastDay'] = $d['lastDay'];

        // кол-во аварий по приоритетам
        $stats['countAccidentPriority1'] = $repository->countAccident($year, $week, 1, false, $filters);
        $stats['countAccidentPriority2'] = $repository->countAccident($year, $week, 2, false, $filters);
        $stats['countAccidentPriority3'] = $repository->countAccident($year, $week, 3, false, $filters);

        // кол-во просроченных аварий по приоритетам
        $stats['countAccidentOverduePriority1'] = $repository->countAccident($year, $week, 1, true, $filters);
        $stats['countAccidentOverduePriority2'] = $repository->countAccident($year, $week, 2, true, $filters);
        $stats['countAccidentOverduePriority3'] = $repository->countAccident($year, $week, 3, true, $filters);

        // общее кол-во аварий
        $stats['countAllAccident'] = $stats['countAccidentPriority1'] + $stats['countAccidentPriority2'] + $stats['countAccidentPriority3'];
        $stats['countAllAccidentOverdue'] = $stats['countAccidentOverduePriority1'] + $stats['countAccidentOverduePriority2'] + $stats['countAccidentOverduePriority3'];

        return $stats;
    }

    /*
    * строим список регионов
    */
    public function selectRegion()
    {
        $json = file_get_contents($this->get('kernel')->getRootDir() . "/Resources/units_structure.json");
        $structure = json_decode($json, JSON_UNESCAPED_UNICODE);
        $region = array();

        foreach ($structure as $region_key => $value) {
            if ($region_key == "email") continue;
            $region[] = $region_key;
        }

        return $region;
    }

    /*
     * строим список филиалов в регионе
     * @Route("/accident/selectFilial", name="accident_selectFilial")
     */
    public function selectFilialAction()
    {
        $json = file_get_contents($this->get('kernel')->getRootDir() . "/Resources/units_structure.json");
        $structure = json_decode($json, JSON_UNESCAPED_UNICODE);
        $filial = array();

        foreach ($structure[$_POST['region']] as $tp_key => $tp_val) {
            if ($tp_key == "email") continue;
            foreach ($tp_val as $filial_key => $filial_val) {
                if ($filial_key == "email") continue;
                $filial[] = $filial_key;
            }
        }

        return $this->render('@Accident/Default/selectFilial.html.twig', array(
            'filial' => $filial
        ));
    }

    /*
     * строим список городов в филиале
     * @Route("/accident/selectCity", name="accident_selectCity")
     */
    public function selectCityAction()
    {
        $json = file_get_contents($this->get('kernel')->getRootDir() . "/Resources/units_structure.json");
        $structure = json_decode($json, JSON_UNESCAPED_UNICODE);
        $city = array();

        foreach ($structure[$_POST['region']] as $tp_key => $tp_val) {
            if ($tp_key == "email") continue;
            foreach ($tp_val as $filial_key => $filial_val) {
                if ($filial_key == $_POST['filial']) {
                    foreach ($filial_val as $city_key => $city_val) {
                        $city[] = $city_key;
                    }
                }
            }
        }

        return $this->render('@Accident/Default/selectCity.html.twig', array(
            'city' => $city
        ));
    }

    /*
     * строим список городов в филиале
     * @Route("/accident/selectDistrict", name="accident_selectDistrict")
     */
    public function selectDistrictAction()
    {
        $json = file_get_contents($this->get('kernel')->getRootDir() . "/Resources/units_structure.json");
        $structure = json_decode($json, JSON_UNESCAPED_UNICODE);
        $district = array();

        foreach ($structure[$_POST['region']] as $tp_key => $tp_val) {
            if ($tp_key == "email") continue;
            foreach ($tp_val as $filial_key => $filial_val) {
                if ($filial_key == $_POST['filial']) {
                    foreach ($filial_val as $city_key => $city_val) {
                        if ($city_key == $_POST['city']) {
                            foreach ($city_val as $district_key => $district_val) {
                                $district[] = $district_key;
                            }
                        }
                    }
                }
            }
        }

        return $this->render('@Accident/Default/selectDistrict.html.twig', array(
            'district' => $district
        ));
    }

    /*
     * Сохраняем комментарий
     * @Route("/accident/saveComment", name="accident_saveComment")
     */
    public function saveCommentAction(Request $request)
    {
        if (!$request->isXmlHttpRequest()) {
            throw new NotFoundHttpException("Этот запрос только через ajax");
        }

        $em = $this->getDoctrine()->getManager();
        $ticket = $em->getRepository('AccidentBundle:Accident')->find($request->request->get('id'));

        if (!$ticket) {
            throw $this->createNotFoundException('Не найден тикет для добавления коментария');
        }

        $ticket->setComments($request->request->get('comment'));
        $ticket->setAuthorComment($request->request->get('fio'));
        $em->flush();

        return new Response("ok");
    }

    /*
     * показываем только просроченные аварии
     * @Route("/accident/viewOverdue", name="accident_viewOverdue")
     */
}
