<?php
namespace App\Services\QrCode;

use Illuminate\Http\File;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Facades\Storage;

class QrCodeFromFile implements QrCode
{
    public function identifyCode($file)
    {
        $localFilename = Storage::put('files', new File($file));
        $upload = $this->uploadFile($localFilename);

        if ($upload->status() != 200) {
            return response()->json(['error' => 'Произошла ошибка при загрзке файла на сервер'], $upload->status());
        }

        $qrcode = $this->checkQrCode($localFilename);

        if (!$qrcode) {
            unlink(storage_path('app') . "/" . $localFilename);
            return response()->json(['error' => 'QR-код не распознан'], 404);
        }

        unlink(storage_path('app') . "/" . $localFilename);
        return response()->json(['qrcode' => $qrcode], 200);
    }

    /**
     * Загружаем файл на сервер распознования qr кодов
     * @param $file
     * @return JsonResponse
     */
    private function uploadFile($file)
    {
        $ftpServer = Config::get('services.qrcode.server');
        $ftpLogin = Config::get('services.qrcode.login');
        $ftpPassword = Config::get('services.qrcode.password');
        $ftpPort = Config::get('services.qrcode.port');
        $ftpConnection = \ftp_connect($ftpServer, $ftpPort, 30);

        if ($ftpConnection === false) {
            $response = [
                "error" => "Подключение к FTP-серверу: ошибка"
            ];
            $statusCode = 409;

            return response()->json($response, $statusCode);
        };

        $authFtp = \ftp_login($ftpConnection, $ftpLogin, $ftpPassword);

        if ($authFtp === false) {
            $response = [
                "error" => "Авторизация на FTP-сервере: ошибка"
            ];
            $statusCode = 409;

            return new JsonResponse($response, $statusCode);
        };

        $remotePath = Config::get('services.qrcode.path_file') . "/" . $file;
        $localPath = storage_path('app/') . $file;
        $upload = ftp_put($ftpConnection, $remotePath, $localPath, FTP_BINARY);

        if ($upload === false) {
            $response = [
                "error" => "Загрузка файла на FTP-сервер: ошибка"
            ];
            $statusCode = 409;

            return new JsonResponse($response, $statusCode);
        }

        return response()->json(['result' => 'success'], 200);
    }

    /**
     * Распознаем qr код на загруженной картинке
     * @param $file
     * @return string
     */
    private function checkQrCode($file)
    {
        $ftpServer = Config::get('services.qrcode.server');
        $sshConnection = ssh2_connect($ftpServer, 22);

        if ($sshConnection === false) {
            $response = [
                "error" => "SSH connect to " . $ftpServer . ": ошибка"
            ];
            $statusCode = 409;

            return new JsonResponse($response, $statusCode);
        }

        $sshAuth = ssh2_auth_password($sshConnection, Config::get('services.qrcode.login'), Config::get('services.qrcode.password'));

        if ($sshAuth === false) {
            $response = [
                "error" => "SSH авторизация на " . $ftpServer . ": ошибка"
            ];
            $statusCode = 409;

            return new JsonResponse($response, $statusCode);
        }

        $script = Config::get('services.qrcode.script') . ' ' . $file;
        $stream = ssh2_exec($sshConnection, $script);
        stream_set_blocking($stream, true);
        $streamOut = ssh2_fetch_stream($stream, SSH2_STREAM_STDIO);
        $qrcode = stream_get_contents($streamOut);

        if ($qrcode == "") {
            return false;
        }

        $qr = explode(":", $qrcode);

        return trim($qr[1]);
    }
}
