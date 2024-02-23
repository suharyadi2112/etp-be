<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Log;

use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

use App\Helpers\Helper as GLog;
//model
use App\Models\Guru;

class UploadToDropboxGuru implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file;
    protected $nip;
    protected $contents;
    protected $accessToken;
    protected $refreshToken;
    protected $clientid;
    protected $secretid;
    protected $urlDropBox;
    protected $urlDropBoxVTwo;

    /**
     * Create a new job instance.
     *
     * @param  string  $file
     * @return void
     */
    public function __construct($file, $nip)
    {
        $this->file = $file;
        $this->nip = $nip;
        $this->refreshToken = env('DROPBOX_REFRESH_TOKEN');
        $this->accessToken = env('DROPBOX_ACCESS_TOKEN');
        $this->clientid = env('DROPBOX_CLIENT_ID');
        $this->secretid = env('DROPBOX_SECRET_ID');
        $this->urlDropBox = env('DROPBOX_URL');
        $this->urlDropBoxVTwo = env('DROPBOX_URL_V2');
    }

    /**
     * Execute the job.
     *
     * @return void
     */
    public function handle()
    {
        try {
            
            $client = new Client();
            $contents = Storage::disk('public')->get($this->file);
            // $token = Dropbox::getAccessToken();

            $this->refreshToken(); //refresh token

            // Konfigurasi request
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer '. $this->accessToken,
                    'Dropbox-API-Arg' => json_encode([
                        'autorename' => false,
                        'mode' => 'add',
                        'mute' => false,
                        'path' => $this->file,
                        'strict_conflict' => false
                    ]),
                    'Content-Type' => 'application/octet-stream',
                ],
                'body' => $contents, // membuka file dalam mode baca biner
            ];
            $response = $client->post($this->urlDropBoxVTwo.'/2/files/upload', $options);
            
            // Mengambil konten respons sebagai string
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);
            
            // Menyimpan respons ke dalam log
            Log::info($responseBody);

            $upGuru = Guru::where('nip', $this->nip)->first();
            $upGuru->path_photo_cloud = $responseData["path_display"]; //update path cloud
            $upGuru->save();


        } catch (\Exception $e) {
            GLog::AddLog('fails send file guru to dropbox', $e->getMessage(), "error"); 
        }
    }


    protected function refreshToken()
    {
        try {
            
            $client = new Client(['base_uri' => $this->urlDropBox.'/oauth2/token']);
            $response = $client->post('token', [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshToken,
                    'client_id' => $this->clientid,
                    'client_secret' => $this->secretid,
                ],
            ]);

            // Handle the response
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);

            Log::info($responseData);
            $this->accessToken = $responseData['access_token']; //assign new token

        } catch (\Exception $e) {
            GLog::AddLog('fails refresh token', $e->getMessage(), "error"); 
        }
    }
}
