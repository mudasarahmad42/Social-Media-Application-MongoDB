<?php

namespace App\Http\Controllers;

use App\Models\FriendRequest;
use App\Models\ReceivedFriendRequest;
use App\Models\SentFriendRequest;
use Illuminate\Http\Request;
use MongoDB\Client as Mongo;
use App\Models\User;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use Throwable;

class FriendRequestController extends Controller
{

    /*
        Function to send a request to another user
        parameter: user_id
    */
    public function sendRequest(Request $request, $id)
    {
        try {
            //Get Bearer Token
            $getToken = $request->bearerToken();

            //Create a connection to mongoDB
            //connect to user collection
            $receiverCollection = (new Mongo())->sma_mongodb->users;

            if (!isset($getToken)) {
                return response([
                    'message' => 'Bearer token not found'
                ]);
            }

            $receiverExists = $receiverCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                ]
            );

            $receiverName = iterator_to_array($receiverExists);

            if (!isset($receiverExists)) {
                return response([
                    'message' => 'Request receiver does not exist'
                ]);
            }

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));
            //Get Id
            $userId = $decoded->data;

            //User can not send request to itself
            if ($userId == $id) {
                return response([
                    'message' => 'You can not send request to yourself'
                ]);
            }

            $senderCollection = (new Mongo())->sma_mongodb->users;


            $getSenderId = $senderCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                    "sentRequests.receiver_id" => $id
                ],
            );

            /* 
            Check if request has been sent to this user before
            */

            if ($getSenderId == null) {

                $saveFriendRequest1 = $senderCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                    ],
                    [
                        '$push' => [
                            'sentRequests' => [
                                '_id' => substr(number_format(time() * rand(), 0, '', ''), 0, 6),
                                'user_id' => $userId,
                                'receiver_id' => $id,
                                'status' => false
                            ]
                        ]
                    ]
                );


                $saveFriendRequest2 = $receiverCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($id),
                    ],
                    [
                        '$push' => [
                            'receivedRequests' => [
                                '_id' => substr(number_format(time() * rand(), 0, '', ''), 0, 6),
                                'user_id' => $id,
                                'sender_id' => $userId,
                                'status' => false
                            ]
                        ]
                    ]
                );

                return response([
                    'message' => 'Request sent to ' . $receiverName['name']
                ]);
            } else {
                return response([
                    'message' => 'Friend request is already pending'
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to show requests of the user
    */
    public function myRequests(Request $request)
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


            //Create a connection to mongoDB
            //connect to user collection
            $userCollection = (new Mongo())->sma_mongodb->users;

            $getUser = $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                ]
            );


            if (isset($getUser['receivedRequests']) == false && isset($getUser['sentRequests']) == false) {
                return response([
                    'message' => 'You have no friend requests'
                ]);
            } else {

                $responseArray[] = null;
                if (isset($getUser['receivedRequests'])) {
                    $responseArray['requests_received'] = $getUser['receivedRequests'];
                }

                if (isset($getUser['sentRequests'])) {
                    $responseArray['requests_sent'] = $getUser['sentRequests'];
                }

                return response([
                    'message' => $responseArray
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to accept a request received by the user.
        Takes id of the user who sent the request as a parameter
        parameter: user_id
    */
    public function acceptRequest(Request $request, $id)
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


            //connect to user collection
            $userCollection = (new Mongo())->sma_mongodb->users;

            //-----

            //Get Object of user
            $getUser = $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                ]
            );


            //check if received requests array exists in document
            //and check if received request has sender_id == id
            if (isset($getUser['receivedRequests'])) {
                $requestsReceived = null;
                foreach ($getUser['receivedRequests'] as $key => $value) {
                    if ($value['sender_id'] == $id) {
                        $requestsReceived = $value;
                    }
                }
            }

            //if the request is valid
            // set received request status as true
            if ($requestsReceived) {
                $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                        "receivedRequests.sender_id" => $id
                    ],
                    [
                        '$set' => ['receivedRequests.$.status' => true]
                    ]
                );
            }

            //-----


            //------

            //Get object of user who sent the request
            $getSender = $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                ]
            );


            if (isset($getSender['sentRequests'])) {
                $requestsSent = null;
                foreach ($getSender['sentRequests'] as $key => $value) {
                    if ($value['user_id'] == $id) {
                        $requestsSent = $value;
                    }
                }
            }


            //set senders requests status
            if ($requestsSent) {
                $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($id),
                        "sentRequests.receiver_id" => $userId
                    ],
                    [
                        '$set' => ['sentRequests.$.status' => true]
                    ]
                );
            }

            //------


            if (isset($requestsReceived)) {

                /*            
                CHECK IF REQUEST IS ALREADY ACCEPTED            
            */

                if ($requestsReceived['status'] ==  true) {
                    return response([
                        'message' => 'Request already accepted'
                    ]);
                }

                return response([
                    'message' => 'Request accepted'
                ]);
            } else {
                return response([
                    'message' => 'You are not allowed to perform this action'
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to delete a request either sent or recieved by you.
        parameter: user_id
    */
    public function deleteRequest(Request $request, $id)
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

            //Create a connection to mongoDB
            //connect to user collection
            $userCollection = (new Mongo())->sma_mongodb->users;

            //Check if request has been received
            $requestsReceived = $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                    "receivedRequests.sender_id" => $id,
                    "receivedRequests.status" => false,
                ]
            );

            //Check if user sent the request
            $requestsSent = $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                    "sentRequests.receiver_id" => $id,
                    "sentRequests.status" => false,
                ]
            );


            if (isset($requestsReceived)) {
                //Check if request has been received
                $requestsReceived = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                        "receivedRequests.sender_id" => $id,
                        "receivedRequests.status" => false,
                    ],
                    [
                        '$pull' => [
                            'receivedRequests' => [
                                'sender_id' => $id,
                                'status' => false
                            ]
                        ]
                    ]
                );

                //Delete its corresponding entry
                $requestsSent = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($id),
                        "sentRequests.receiver_id" => $userId,
                        "sentRequests.status" => false,
                    ],
                    [
                        '$pull' => [
                            'sentRequests' => [
                                'receiver_id' => $userId,
                                'status' => false
                            ]
                        ]
                    ]
                );

                return response([
                    'message' => 'Friend removed'
                ]);
            }

            if (isset($requestsSent)) {

                //Delete its corresponding entry from sent friend request table
                $requestsSent = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                        "sentRequests.receiver_id" => $id,
                        "sentRequests.status" => false,
                    ],
                    [
                        '$pull' => [
                            'sentRequests' => [
                                'receiver_id' => $id,
                                'status' => false
                            ]
                        ]
                    ]
                );

                //Check if request has been received
                $requestsReceived = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                        "receivedRequests.sender_id" => $id,
                        "receivedRequests.status" => false,
                    ],
                    [
                        '$pull' => [
                            'receivedRequests' => [
                                'sender_id' => $id,
                                'status' => false
                            ]
                        ]
                    ]
                );

                return response([
                    'message' => 'You have removed the friend'
                ]);
            }

            return response([
                'message' => 'No such friend exists'
            ]);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }



    /*
        Function to remove a friend from the list.
        parameter: user_id
    */
    public function removeFriend(Request $request, $id)
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

            //Create a connection to mongoDB
            //connect to user collection
            $userCollection = (new Mongo())->sma_mongodb->users;

            //Check if request has been received
            $requestsReceived = $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                    "receivedRequests.sender_id" => $id,
                    "receivedRequests.status" => true,
                ]
            );

            //Get corresponding entry from sent request table too to change status in both tables
            $requestsSent = $userCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($userId),
                    "sentRequests.receiver_id" => $id,
                    "sentRequests.status" => true,
                ]
            );


            if (isset($requestsReceived)) {
                //Check if request has been received
                $requestsReceived = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                        "receivedRequests.sender_id" => $id,
                        "receivedRequests.status" => true,
                    ],
                    [
                        '$pull' => [
                            'receivedRequests' => [
                                'sender_id' => $id,
                                'status' => true
                            ]
                        ]
                    ]
                );

                //Delete its corresponding entry from sent friend request table
                $requestsSent = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($id),
                        "sentRequests.receiver_id" => $userId,
                        "sentRequests.status" => true,
                    ],
                    [
                        '$pull' => [
                            'sentRequests' => [
                                'receiver_id' => $userId,
                                'status' => true
                            ]
                        ]
                    ]
                );

                return response([
                    'message' => 'Friend removed'
                ]);
            }

            if (isset($requestsSent)) {

                //Delete its corresponding entry from sent friend request table
                $requestsSent = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                        "sentRequests.receiver_id" => $id,
                        "sentRequests.status" => true,
                    ],
                    [
                        '$pull' => [
                            'sentRequests' => [
                                'receiver_id' => $id,
                                'status' => true
                            ]
                        ]
                    ]
                );

                //Check if request has been received
                $requestsReceived = $userCollection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($userId),
                        "receivedRequests.sender_id" => $id,
                        "receivedRequests.status" => true,
                    ],
                    [
                        '$pull' => [
                            'receivedRequests' => [
                                'sender_id' => $id,
                                'status' => true
                            ]
                        ]
                    ]
                );

                return response([
                    'message' => 'You have removed the friend'
                ]);
            }

            return response([
                'message' => 'No such friend exists'
            ]);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
