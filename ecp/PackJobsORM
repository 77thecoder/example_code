<?php


namespace App\Services\PackJobs;


use App\Http\Requests\PackJobs\PackJobGetListByLoginRequest;
use App\Http\Requests\PackJobs\PackJobGetListByTNRequest;
use App\Http\Requests\PackJobs\PackJobsAddRequest;
use App\Http\Requests\PackJobs\PackJobsByQRRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class PackJobsORM implements PackJobsInterface
{
    /**
     * Добавление статуса работ
     * @param Request $request
     */
    public function add(PackJobsAddRequest $request)
    {
        $params = $request->all();
        try {
            DB::select("call Pack_ProvenJobsAdd(:qrcodepacket, :status, :datestatus, :verifierlogin, :verifiertn)", [
                    'qrcodepacket' => $params['qrcodepacket'],
                    'status' => $params['status'],
                    'datestatus' => $params['datestatus'],
                    'verifierlogin' => $params['verifierlogin'],
                    'verifiertn' => $params['verifiertn']
                ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => $e->getSql(),
                'detail' => $e->getMessage(),
                'request_uri' => (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : $_SERVER['argv']
            ], 400);
        }

        return response()->json(['result' => 'success']);
    }

    /**
     * Получить список пачек со статусами проверки работ
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListProvenJobs()
    {
        try {
            $result = DB::select("call Pack_GetListProvenJobs()");
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => $e->getSql(),
                'detail' => $e->getMessage(),
                'request_uri' => (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : $_SERVER['argv']
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * Список пачек со статусами проверки работ по логину сотрудника
     * @param string $login
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListProvenJobsByVerifierLogin(PackJobGetListByLoginRequest $request)
    {
        $login = $request->get('verifierlogin');

        try {
            $result = DB::select("call Pack_GetListProvenJobsByVerifierLogin(:login)", [
                'login' => $login
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => $e->getSql(),
                'detail' => $e->getMessage(),
                'request_uri' => (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : $_SERVER['argv']
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * Список пачек по табельному номеру сотрудника
     * @param PackJobGetListByTNRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getListProvenJobsByVerifierTN(PackJobGetListByTNRequest $request)
    {
        $tn = $request->get('verifiertn');

        try {
            $result = DB::select("call Pack_GetListProvenJobsByVerifierTN(:tn)", [
                'tn' => $tn
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => $e->getSql(),
                'detail' => $e->getMessage(),
                'request_uri' => (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : $_SERVER['argv']
            ], 400);
        }

        return response()->json($result);
    }

    /**
     * Информация о проверке работ по коду пачек
     * @param PackJobsByQRRequest $request
     * @return \Illuminate\Http\JsonResponse
     */
    public function getProvenJobsByQR(PackJobsByQRRequest $request)
    {
        $qrcode = $request->get('qrcodepacket');

        try {
            $result = DB::select("call Pack_GetProvenJobsByQR(:qrcode)", [
                'qrcode' => $qrcode
            ]);
        } catch (\Illuminate\Database\QueryException $e) {
            return response()->json([
                'message' => $e->getSql(),
                'detail' => $e->getMessage(),
                'request_uri' => (isset($_SERVER['REQUEST_URI'])) ? $_SERVER['REQUEST_URI'] : $_SERVER['argv']
            ], 400);
        }

        return response()->json($result);
    }

}
