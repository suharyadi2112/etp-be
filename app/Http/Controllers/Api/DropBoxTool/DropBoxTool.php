<?php

namespace App\Http\Controllers\Api\DropBoxTool;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;
use Illuminate\Support\Facades\Http;

use App\Helpers\Helper as GLog;

class DropBoxTool extends Controller
{

    private $refreshtoken;
    private $clientid;
    private $secretid;
    private $newaccesstoken;
    private $temporaryLinkPhoto;
    protected $urlDropBox;
    protected $urlDropBoxVTwo;

    public function __construct()
    {
        $this->refreshtoken = env('DROPBOX_REFRESH_TOKEN');
        $this->clientid = env('DROPBOX_CLIENT_ID');
        $this->secretid = env('DROPBOX_SECRET_ID');
        $this->urlDropBox = env('DROPBOX_URL');
        $this->urlDropBoxVTwo = env('DROPBOX_URL_V2');
    }
    
    public function RefreshTokenDropbox(){
        try {
                  
            $client = new Client(['base_uri' => $this->urlDropBox.'/oauth2/token']);
            $response = $client->post('token', [
                RequestOptions::FORM_PARAMS => [
                    'grant_type' => 'refresh_token',
                    'refresh_token' => $this->refreshtoken,
                    'client_id' => $this->clientid,
                    'client_secret' => $this->secretid,
                ],
            ]);
    
            // Handle the response
            $responseBody = $response->getBody()->getContents();
            $responseData = json_decode($responseBody, true);
            
            $this->newaccesstoken = $responseData['access_token']; //assign new token
            
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved token', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }

    public function GetTemporaryLink(Request $request){
        try {
            
            $this->RefreshTokenDropbox();

            $response = Http::withHeaders([
                'Content-Type' => 'application/json',
                'Authorization' => 'Bearer ' . $this->newaccesstoken,
            ])->post($this->urlDropBox.'/2/files/get_temporary_link', [
                'path' => $request->realpath,
            ]);
            $result = $response->json();
            $temporaryLink = $result['link'];
            $this->temporaryLinkPhoto = $temporaryLink;

            return response(["status"=> "success","message"=> "Link successfully retrieved", "data" => $this->temporaryLinkPhoto], 200);

        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved token', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
    }
}
