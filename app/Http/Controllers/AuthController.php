<?php

namespace App\Http\Controllers;

use App\Mail\ConfirmEmail;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use MongoDB\Client as Mongo;
use MongoDB\Operation\UpdateOne;
use Throwable;

class AuthController extends Controller
{
    /*
        Function to create a JWT token
        parameter: user_id
    */
    function createToken($data)
    {
        $key = "ProgrammersForce";
        $payload = array(
            "iss" => "http://127.0.0.1:8000",
            "aud" => "http://127.0.0.1:8000/api",
            "iat" => time(),
            "nbf" => 1357000000,
            'exp' => time() + 3600,
            "data" => $data,
        );

        $jwt = JWT::encode($payload, $key, 'HS256');

        return $jwt;
    }

    /*
        Function to create a temporary JWT token that is used to
        verify user's account
        parameter: time()
    */
    function createTempToken($data)
    {
        $key = "ProgrammersForce";
        $payload = array(
            "iss" => "http://127.0.0.1:8000",
            "aud" => "http://127.0.0.1:8000/api",
            "iat" => time(),
            "nbf" => 1357000000,
            'exp' => time() + 1000,
            "data" => $data,
        );

        $jwt = JWT::encode($payload, $key, 'HS256');

        return $jwt;
    }

    /*
        Function to create a new user
    */
    public function register(Request $request)
    {

        try {
            $collection = (new Mongo())->sma_mongodb->users;

            //Validate the fields
            $fields = $request->validate(
                [
                    'name' => 'required',
                    'email' => 'required',
                    'password' => 'required'
                ]
            );

            //Create one time token
            $tempToken = $this->createTempToken(time());


            $user = $collection->insertOne(
                [
                    'name' => $fields['name'],
                    'email' => $fields['email'],
                    'password' => bcrypt($fields['password']),
                    'remember_token' => $tempToken,
                    'email_verified_at' => null,
                    'token' => ['login_token' => null],
                    // 'sent_friend_requests' => ['receiver_id' => ''],
                    // 'received_friend_requests' => ['sender_id' => ''],
                ],

            );

            //Send Email
            $url = url('api/EmailConfirmation/' . $fields['email'] . '/' . $tempToken);

            Mail::to($fields['email'])->send(new ConfirmEmail($url, 'batalew787@ecofreon.com'));

            $response = [
                'message' => 'User has been created successfully',
                'user' => $user,
                'Mail response' => 'Email sent succesfully'
            ];

            //Return HTTP 201 status, call was successful and something was created
            return response($response, 201);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to login the user by assigning a token to it
        parameter: user_id
    */
    public function login(Request $request)
    {
        try {
            $fields = $request->validate([
                'email' => 'required|string',
                'password' => 'required|string'
            ]);

            //Create a connection to mongoDB
            $collection = (new Mongo())->sma_mongodb->users;

            $userCollection =  $collection->findOne(
                [
                    'email' => $fields['email']
                ]
            );

            if ($userCollection == null) {
                return response([
                    'message' => 'No such user found'
                ], 404);
            }

            $user = iterator_to_array($userCollection);

            // Check password
            if (!$userCollection || !Hash::check($fields['password'], $user['password'])) {
                return response([
                    'message' => 'Invalid email or password'
                ], 401);
            }


            if ($user['email_verified_at'] == null) {
                return response([
                    'message' => 'Your email is not confirmed'
                ]);
            }

            //Check if user is already logged in
            $isLoggedIn = $userCollection['token'];

            if ($isLoggedIn['login_token']) {
                return response([
                    'message' => 'User already logged-in'
                ], 400);
            }

            $token = $this->createToken((string)$user['_id']);

            //Store token in database
            $collection->updateOne(
                ['email' => $user['email']],
                ['$set' => ['token' => ['login_token' => $token]]],
            );

            $response = [
                'message' => 'Logged in successfully',
                'user' => $user,
                'token' => $token
            ];

            return response($response, 201);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to logout the user by deleting its JWT token
    */
    public function logout(Request $request)
    {
        try {
            //Get Bearer Token
            $getToken = $request->bearerToken();

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));

            //Get Id
            $getUserId = $decoded->data;

            //Create a connection to mongoDB
            $collection = (new Mongo())->sma_mongodb->users;

            $userCollection =  $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($getUserId),
                ]
            );

            $userCollectionArray = iterator_to_array($userCollection);
            $userExists =  $userCollectionArray['token'];

            if (isset($userExists['login_token'])) {

                //Delete Token
                $collection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($getUserId),
                    ],
                    ['$set' => ['token' => ['login_token' => null]]],
                );
            } else {
                $message = [
                    'message' => 'This user is already logged out'
                ];

                return response($message, 404);
            }

            return [
                'message' => 'Logout Succesfully'
            ];
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }



    /*
        Function to confirm the user registration by email
        this function can be hit through the link sent to the user
        to their email address
        
        parameter: user_id
    */
    public function EmailConfirmation($email, $token)
    {
        try {
            //Create a connection to mongoDB
            $collection = (new Mongo())->sma_mongodb->users;
            $userExists = iterator_to_array($collection->findOne(['email' => $email]));

            if (!$userExists) {
                return response([
                    'message' => 'User does not exists'
                ]);
            }


            if ($userExists['remember_token']) {
                $userToken = $userExists['remember_token'];

                if ($userToken != $token) {
                    return response([
                        'message' => 'You are not authorized to use this link'
                    ]);
                }
            }



            if ($userExists['email_verified_at'] != null) {
                return response([
                    'message' => 'Your link has expired'
                ]);
            } else {

                $collection->updateOne(
                    ['email' => $email],
                    ['$set' => ['email_verified_at' => date("d-m-Y h:i:s A")]]
                );

                return response([
                    'message' => 'Email Confirmed'
                ]);
            }

            return response([
                'message' => 'Something went wrong'
            ]);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
