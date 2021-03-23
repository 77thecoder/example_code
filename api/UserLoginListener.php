<?php

namespace App\Listeners;

use App\Events\UserLoginEvent;
use App\Http\Requests\SessionCreateRequest;
use App\Http\Requests\UserCreateRequest;
use App\Models\Session;
use App\Models\User;
use Illuminate\Support\Facades\Config;

class UserLoginListener
{
    private User $user;

    /**
     * Create the event listener.
     *
     * @return void
     */
    public function __construct()
    {
        //
    }

    /**
     * Handle the event.
     *
     * @param  UserLoginEvent  $event
     * @return void
     */
    public function handle(UserLoginEvent $event)
    {
        $login = strtolower($event->user->login);
        $result = User::where('login', 'regexp', "/^{$event->user->login}/i")->limit(1)->get();
        $this->user = $result[0];

        if (count($this->user->subordinates)) {
            $this->processingSubordinates();
        }

        if (count($this->user->who_replaces)) {
            $this->processingWhoReplaces();
        }

        if (count($this->user->whom_replaces)) {
            $this->processingWhomReplaces();
        }

        $this->processingHead();
    }

    private function processingSubordinates()
    {
        $subordinates = $this->user->subordinates;
        $subordinateList = [];

        foreach ($subordinates as $keySubordinate => $subordinate) {
            $subUser = User::whereTn($subordinate['tn'])->limit(1)->get();

            if (!$subUser->count()) {
                $userCreated = $this->createUser($subordinate['login']);
                $subordinateList[$keySubordinate] = $subordinate;
                $subordinateList[$keySubordinate]['user_id'] = $userCreated->id;
                $this->createSession($userCreated);
            } else {
                $subordinateList[$keySubordinate] = $subordinate;
                $subordinateList[$keySubordinate]['user_id'] = $subUser[0]->id;
            }
        }

        $this->user->subordinates = $subordinateList;
        $userService = app()->make('App\Interfaces\UserInterface');
        $userParams = $this->user->toArray();
        $userService->update(new UserCreateRequest($userParams));
    }

    /**
     * @param string $login
     * @param bool $userID
     * @return User
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     * @throws \JsonMapper_Exception
     */
    private function createUser(string $login, bool $userID = true): User
    {
        $userInfoService = app()->make('App\Interfaces\UserInfoInterface');
        $user = $userInfoService->getUserInfoByLogin($login);
        if ($userID) {
            $user->head_id = $this->user->id;
        }
        $userService = app()->make('App\Interfaces\UserInterface');
        $userParams = $user->toArray();
        $userCreated = $userService->create(new UserCreateRequest($userParams));
        return $userCreated;
    }

    /**
     * @param User $user
     * @return Session
     * @throws \Illuminate\Contracts\Container\BindingResolutionException
     */
    private function createSession(User $user): Session
    {
        $sessionService = app()->make('App\Interfaces\SessionInterface');

        $params = [
            'user_id' => $user->_id,
            'token' => '',
            'expires_in' => Config::get('jwt.ttl')
        ];

        return $sessionService->create(new SessionCreateRequest($params));
    }

    private function processingHead()
    {
        $headLogin = $this->user->head_id;
        $headUser = User::where('login', 'regexp', "/^{$headLogin}/i")->limit(1)->get();

        if (!count($headUser)) {
            $userHead = $this->createUser($headLogin, false);
            $this->user->head_id = $userHead->id;
            $userService = app()->make('App\Interfaces\UserInterface');
            $userParams = $this->user->toArray();
            $userUpdated = $userService->update(new UserCreateRequest($userParams));
            return $userUpdated;
        }
    }

    private function processingWhoReplaces()
    {
        $replaces = $this->user->who_replaces;
        $replaceList = [];

        foreach ($replaces as $keyReplace => $replace) {
            $subUser = User::where('login', 'regexp', "/^{$replace['login']}/i")->limit(1)->get();

            if (!$subUser->count()) {
                $userCreated = $this->createUser($replace['login']);
                $replaceList[$keyReplace] = $replace;
                $replaceList[$keyReplace]['user_id'] = $userCreated->id;
                $this->createSession($userCreated);
            } else {
                $replaceList[$keyReplace] = $replace;
                $replaceList[$keyReplace]['user_id'] = $subUser[0]->id;
            }
        }

        $this->user->who_replaces = $replaceList;
        $userService = app()->make('App\Interfaces\UserInterface');
        $userParams = $this->user->toArray();
        $userService->update(new UserCreateRequest($userParams));
    }

    private function processingWhomReplaces()
    {
        $replaces = $this->user->whom_replaces;
        $replaceList = [];

        foreach ($replaces as $keyReplace => $replace) {
            $subUser = User::where('login', 'regexp', "/^{$replace['login']}/i")->limit(1)->get();

            if (!$subUser->count()) {
                $userCreated = $this->createUser($replace['login']);
                $replaceList[$keyReplace] = $replace;
                $replaceList[$keyReplace]['user_id'] = $userCreated->id;
                $this->createSession($userCreated);
            } else {
                $replaceList[$keyReplace] = $replace;
                $replaceList[$keyReplace]['user_id'] = $subUser[0]->id;
            }
        }

        $this->user->whom_replaces = $replaceList;
        $userService = app()->make('App\Interfaces\UserInterface');
        $userParams = $this->user->toArray();
        $userService->update(new UserCreateRequest($userParams));
    }
}
