<?php

namespace App\Http\Middleware;

use Closure;
use App\Models\SessionToken;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Carbon;

class KeyServiceCheck
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle($request, Closure $next)
    {

        $key_request = $request->header('key-service');
        $timestamp = $request->header('timestamp');
        $data = [ 'ip' => $request->getClientIp(true),
                'agent' => $request->server('HTTP_USER_AGENT'),
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'referer' => $request->headers->get('referer'),
                'content_type' => $request->headers->get('Content-Type'),
                'ips' => $request->ips(),
                'host' => $request->getHost(),
            ];

        if(empty($key_request) || empty($timestamp))
        {
            Log::error('Incomplete Parameter', $data);
            return response()->json(
                [
                'status'    =>  400,
                'success' => false,
                'message' => 'Incomplete Parameters.',
                'data' => []
                ], 400);
        }


        $timestampValid = date("Y-m-d H:i:s", strtotime($timestamp));
    
        if ($timestampValid !== $timestamp || date("Y-m-d", strtotime($timestamp)) !== date("Y-m-d")) {
          
            Log::error('Incomplete Parameter', $data);
            return response()->json(
                [
                'status'    =>  400,
                'success' => false,
                'message' => 'Invalid Timestamp data format.',
                'data' => []
                ], 400);
        } 
  
        $Encryp = env('KEY_SSO') . $timestamp;
        $KeyGenerate = hash(env('KEY_HASH'), $Encryp);

        if($key_request != $KeyGenerate){
            if(env('APP_ENV') != 'development')
            {
                $KeyGenerate = null;
            }
        
            Log::error('Invalid Credentials.', $data);
            return response()->json(
                [
                'status'    =>  400,    
                'success' => false,
                'message' => 'Invalid Credentials.',
                'data' => [$KeyGenerate]
                ], 400);
        }
        
        return $next($request);
    }
}