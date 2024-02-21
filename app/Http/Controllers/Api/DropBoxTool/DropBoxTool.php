<?php

namespace App\Http\Controllers\Api\DropBoxTool;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use GuzzleHttp\Client;
use GuzzleHttp\RequestOptions;

use App\Helpers\Helper as GLog;

class DropBoxTool extends Controller
{

    private $refreshtoken;
    private $clientid;
    private $secretid;

    public function __construct()
    {
        $this->refreshtoken = env('DROPBOX_REFRESH_TOKEN');
        $this->clientid = env('DROPBOX_CLIENT_ID');
        $this->secretid = env('DROPBOX_SECRET_ID');
    }
    
    public function RefreshTokenDropbox(){
        try {
                  
            $client = new Client(['base_uri' => 'https://api.dropboxapi.com/oauth2/']);
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
            
            $accessToken = $responseData['access_token']; //assign new token
            
            return response(["status"=> "success","message"=> "Token successfully retrieved", "data" => $accessToken], 200);
            return $accessToken;
    
        } catch (\Exception $e) {
            GLog::AddLog('fails retrieved token', $e->getMessage(), "error"); 
            return response(["status"=> "fail","message"=> $e->getMessage(),"data" => null], 500);
        }
      }
}
