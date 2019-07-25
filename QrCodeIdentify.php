<?php

namespace App\Http\Controllers\QrCode;

use App\Services\QrCode\QrCodeFactory;
use Illuminate\Http\Request;
use App\Http\Controllers\Controller;

class QrCodeIdentify extends Controller
{
    protected $store;
    protected $type;
    protected $src;

    public function __construct(Request $request)
    {
        $data = $request->all();
        $this->type = $data['type'];
        $this->src = $data['src'];
    }

    public function identifyCode()
    {
        $qrcode = QrCodeFactory::qrCode($this->type);
        $response = $qrcode->identifyCode($this->src);

        return $response;
    }
}
