<?php

namespace App\Services;

use App\Http\Requests\SessionCreateRequest;
use App\Interfaces\SessionInterface;
use App\Models\Session;
use App\Models\User;
use Carbon\Carbon;
use Exception;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use MongoDB\Driver\Exception\WriteException;

class SessionORMService implements SessionInterface
{

    /**
     * Coздать сессию
     * @param SessionCreateRequest $request
     * @return Session
     */
    public function create(SessionCreateRequest $request): Session
    {
        $session = Session::create($request->all())->get();

        return $session[0];
    }

    /**
     * Обновить сессию
     * @param Request $request
     * @return Session|JsonResponse
     */
    public function update(Request $request): Session
    {
        $params = $request->all();
        $session = Session::find($params['_id']);

        try {
            $session->update($params);
            $session->touch();
        } catch (WriteException $e) {
            return response()->json([
                'message' => $e->getMessage()
            ], 400);
        }

        return $session;
    }

    /**
     * Удалить сессию
     * @param string $id
     * @return JsonResponse
     */
    public function delete(string $id): JsonResponse
    {
        Session::find($id)->delete();
        return response()->json([
            'message' => 'Сессия удалена',
        ]);
    }

    /**
     * Сессия пользователя по его ID
     * @param string $userID
     * @return Session
     */
    public function getSessionByUser(): JsonResponse
    {
        $session = auth()->user()->session;
        return response()->json($session->toArray());
    }

    /**
     * Проверка актуальности сессии
     * @param User $user
     * @return bool
     * @throws Exception
     */
    public function checkActualSession(Session $session): bool
    {
        $expiresIn = $session->expires_in;
        $updateAt = $session->updated_at;
        $currentTime = Carbon::now();
        $diff = $currentTime->diffInMinutes($updateAt);

        if ($diff > $expiresIn) {
            $session->delete();
            return false;
        }

        return true;
    }
}
