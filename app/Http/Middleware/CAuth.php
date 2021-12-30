<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use App\Models\Token;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use MongoDB\Client as Mongo;

class CAuth
{
    /**
     * Handle an incoming request.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  \Closure  $next
     * @return mixed
     */
    public function handle(Request $request, Closure $next)
    {
        //Get Bearer Token
        $getToken = $request->bearerToken();

        if(!$getToken)
        {
            return response([
                'message' => 'Bearer token not found'
            ]);
        }
        
        //Decode
        $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));

        //Get Id
        $userId = $decoded->data;

        //Create a connection to mongoDB
        $collection = (new Mongo())->sma_mongodb->users;

        $userCollection =  $collection->findOne(
            [
                '_id' => new \MongoDB\BSON\ObjectId($userId),
            ]
        );

        //Check if user is already logged in
        $isLoggedIn = $userCollection['token'];

        $userExists = $isLoggedIn['login_token'];

        if (isset($userExists)) {
            return $next($request);
        } else {
            return response([
                'message' => 'Unauthorized'
            ],401);
        }
    }
}