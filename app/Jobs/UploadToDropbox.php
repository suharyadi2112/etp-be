<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Helper as GLog;

class UploadToDropbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file;
    protected $contents;
    protected $accessToken;
    protected $refreshToken;

    /**
     * Create a new job instance.
     *
     * @param  string  $file
     * @return void
     */
    public function __construct($file)
    {
        $this->file = $file;
        $this->refreshToken = env('DROPBOX_REFRESH_TOKEN');
        $this->accessToken = env('DROPBOX_ACCESS_TOKEN');
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
            $client->post('https://content.dropboxapi.com/2/files/upload', $options);

        } catch (\Exception $e) {
            GLog::AddLog('fails send file siswa to dropbox', $e->getMessage(), "error"); 
        }
    }

    public function makeRequest($method, $url, $data = [])
    {
        if ($this->isTokenExpired()) { 
            $this->refreshToken();
        }
    }

    protected function isTokenExpired()
    {
        return false;
    }

    protected function refreshToken()
    {
        // Gunakan HTTP Client Laravel untuk melakukan permintaan ke Dropbox
        $response = Http::post('https://api.dropbox.com/oauth2/token', [
            'grant_type' => 'refresh_token',
            'refresh_token' => $this->refreshToken,
            'client_id' => env('DROPBOX_CLIENT_ID'),
            'client_secret' => env('DROPBOX_CLIENT_SECRET'),
        ]);

        // Ambil respons dan simpan token baru
        $responseData = $response->json();
        $this->accessToken = $responseData['access_token'];

    }
}
