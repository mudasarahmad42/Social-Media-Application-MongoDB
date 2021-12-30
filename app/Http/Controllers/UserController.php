<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Exception;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use MongoDB\Client as Mongo;
use MongoDB\Operation\UpdateOne;
use Throwable;

class UserController extends Controller
{
    /*
        Returns user profile
        parameter: user_id
    */
    public function myProfile(Request $request)
    {
        try {

            //Get Bearer Token
            $getToken = $request->bearerToken();

            if (!isset($getToken)) {
                return response([
                    'message' => 'Bearer token not found'
                ]);
            }

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));
            //Get Id
            $userId = $decoded->data;

            if ($userId) {

                //Create a connection to mongoDB
                $collection = (new Mongo())->sma_mongodb->users;

                $userCollection =  $collection->findOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                    ]
                );

                $userCollectionArray = iterator_to_array($userCollection);

                if (isset($userCollectionArray)) {
                    return $userCollectionArray;
                } else {
                    return response([
                        'message' => 'No user found'
                    ], 404);
                }
            }
        } catch (Throwable $e) {
            return response([
                'message' => $e
            ], 404);
        }
    }



    /*
        update user's data
    */
    public function update(Request $request)
    {

        try {
            //Get Bearer Token
            $getToken = $request->bearerToken();

            if (!isset($getToken)) {
                return response([
                    'message' => 'Bearer token not found'
                ]);
            }

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));
            //Get Id
            $userId = $decoded->data;


            if ($userId) {
                //Create a connection to mongoDB
                $collection = (new Mongo())->sma_mongodb->users;

                $userCollection =  $collection->findOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                    ]
                );

                $data_to_update = [];
                foreach ($request->all() as $key => $value) {
                    if (in_array($key, ['name', 'email'])) {
                        $data_to_update[$key] = $value;
                    }
                }

                if (isset($userCollection)) {
                    $collection->updateOne(
                        [
                            '_id' => new \MongoDB\BSON\ObjectId($userId)
                        ],
                        ['$set' => $data_to_update]
                    );
                }

                return response([
                    'message' => 'Profile Updated Successfully'
                ], 404);
            } else {
                return response([
                    'message' => 'You are not authorized to perform this action'
                ], 401);
            }
        } catch (Throwable $e) {
            return response([
                'message' => $e->getMessage()
            ], 404);
        }
    }


    /*
        delete user's data
    */
    public function delete(Request $request)
    {
        try {
            //Get Bearer Token
            $getToken = $request->bearerToken();

            if (!isset($getToken)) {
                return response([
                    'message' => 'Bearer token not found'
                ]);
            }

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));
            //Get Id
            $userId = $decoded->data;

            $collection = (new Mongo())->sma_mongodb->users;

            $userCollection =  $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                ]
            );

            if ($userCollection == null) {
                return response([
                    'message' => 'No user found'
                ], 404);
            }

            $userCollectionArray = iterator_to_array($userCollection);

            if (isset($userCollectionArray)) {
                $collection->deleteMany(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                    ]
                );

                return response([
                    'message' => 'User Deleted successfully'
                ], 404);
            } else {
                return response([
                    'message' => 'Something went wrong'
                ], 404);
            }
        } catch (Throwable $e) {
            return response([
                'message' => $e->getMessage()
            ], 404);
        }
    }


    /*
        search user's by name
    */
    public function searchByName($name)
    {
        try {
            $collection = (new Mongo())->sma_mongodb->users;

            $userCollection =  $collection->findOne(
                [
                    'name' => ['$regex' =>  $name],
                ]
            );

            if ($userCollection == null) {
                return response([
                    'message' => 'No user found'
                ], 404);
            }

            return $userCollection;
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
