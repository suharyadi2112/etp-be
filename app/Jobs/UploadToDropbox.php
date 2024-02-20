<?php

namespace App\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use GuzzleHttp\Client;
use Illuminate\Support\Facades\Storage;
use App\Helpers\Helper as GLog;

class UploadToDropbox implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $file;
    protected $contents;

    /**
     * Create a new job instance.
     *
     * @param  string  $file
     * @return void
     */
    public function __construct($file)
    {
        $this->file = $file;
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
            $accessToken = env('DROPBOX_ACCESS_TOKEN');
            $contents = Storage::disk('public')->get($this->fie);

            // Konfigurasi request
            $options = [
                'headers' => [
                    'Authorization' => 'Bearer '. $accessToken,
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
}
