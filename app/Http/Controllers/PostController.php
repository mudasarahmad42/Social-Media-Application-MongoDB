<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\ReceivedFriendRequest;
use App\Models\SentFriendRequest;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;
use MongoDB\Client as Mongo;
use MongoDB\Collection;
use MongoDB\Driver\Cursor;
use PDO;
use Throwable;

use function PHPUnit\Framework\isEmpty;

class PostController extends Controller
{

    /*
        Returns user posts
    */
    public function findAll(Request $request)
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
            $collection = (new Mongo())->sma_mongodb->posts;

            $postCollection = $collection->find([
                'user_id' => $userId
            ]);


            if ($postCollection->isDead()) {
                return response([
                    'message' =>  'No posts exists'
                ]);
            }

            return response([
                'message' =>  $postCollection->toArray()
            ]);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to find a post by id
        returns if the post is yours
        parameter: post_id
    */
    public function findById(Request $request, $id)
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

        /* New Code starts from here */
        $userCollection = (new Mongo())->sma_mongodb->users;


        //Users sent requests
        $sentRequests = $userCollection->findOne(
            [
                '_id' => new \MongoDB\BSON\ObjectId($userId),
                "sentRequests.status" => true
            ],
        );

        if ($sentRequests !=  null) {

            if (!$sentRequests->isDead()) {
                //Array of friends to whom i sent the request
                foreach ($sentRequests as $row) {
                    $friendSentIds[] = $row['receiver_id'];
                }
            } else {
                $friendSentIds = ['receiver_id' => null];
            }
        }


        //Arrays
        $friendReceivedIds = ['sender_id' => null];
        $friendSentIds = ['receiver_id' => null];

        //Users received requests
        $recievedRequests = $userCollection->findOne(
            [
                '_id' => new \MongoDB\BSON\ObjectId($userId),
                "receivedRequests.status" => true
            ],
        );

        if ($recievedRequests !=  null) {

            if (!$recievedRequests->isDead()) {
                //Array of friends who sent me the request
                foreach ($recievedRequests as $row) {
                    $friendReceivedIds[] = $row['sender_id'];
                }
            } else {
                $friendReceivedIds = ['sender_id' => null];
            }
        }

        //connect to posts collection
        $postCollection = (new Mongo())->sma_mongodb->posts;

        //Check if request has been received
        $getPost = $postCollection->findOne(
            [
                '_id' => new \MongoDB\BSON\ObjectId($id),
            ]
        );

        if ($getPost == null) {
            return response([
                'message' => 'No such post exists'
            ]);
        }

        //user_id of author of this post
        $author = $getPost['user_id'];


        if (in_array($author, $friendReceivedIds) || in_array($author, $friendSentIds) || $author == $userId || $getPost['privacy'] == '0') {

            if (isset($getPost)) {
                return $getPost;
            } else {
                return response([
                    'message' => 'No Post found'
                ], 404);
            }
        } else {
            return response([
                'message' => 'You are not allowed to access this post'
            ], 404);
        }

        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to create a post.
    */
    public function create(Request $request)
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


            $request->validate([
                'title' => 'required',
                'body' => 'required',
            ]);



            //Create a connection to mongoDB
            $collection = (new Mongo())->sma_mongodb->posts;


            if ($request->file('attachment') != null) {
                $file = $request->file('attachment')->store('postFiles');

                $collection->insertOne(
                    [
                        'user_id' => $userId,
                        'title' => $request->title,
                        'body' => $request->body,
                        'privacy' => '0',
                        'attachment' => 'http://127.0.0.1:8000/storage/app/' . $file,
                    ],
                );
                return response([
                    'message' => 'Posts created successfully'
                ]);
            } else {
                $collection->insertOne(
                    [
                        'user_id' => $userId,
                        'title' => $request->title,
                        'body' => $request->body,
                        'privacy' => '0',
                    ],
                );
                return response([
                    'message' => 'Posts submitted successfully'
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }



    /*
        Function to update a post
        parameter: post_id
    */
    public function update(Request $request, $id)
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
            $collection = (new Mongo())->sma_mongodb->posts;

            $data_to_update = [];
            foreach ($request->all() as $key => $value) {
                if (in_array($key, ['title', 'body'])) {
                    $data_to_update[$key] = $value;
                }
            }

            $postCollection = $collection->findOne([
                '_id' => new \MongoDB\BSON\ObjectId($id),
                'user_id' => $userId
            ]);


            if ($postCollection == null) {
                return response([
                    'message' => 'No such post exists'
                ]);
            }

            if (!empty($postCollection)) {
                $collection->updateOne(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($id),
                        'user_id' => $userId
                    ],
                    ['$set' => $data_to_update]
                );


                if ($request->file('attachment') != null) {
                    $file = $request->file('attachment')->store('postFiles');

                    $collection->updateOne(
                        [
                            '_id' => new \MongoDB\BSON\ObjectId($id),
                            'user_id' => $userId
                        ],
                        ['$set' => ['attachment' => 'http://127.0.0.1:8000/storage/app/' . $file]]
                    );
                }

                if ($request->privacy != null) {
                    $collection->updateOne(
                        [
                            '_id' => new \MongoDB\BSON\ObjectId($id),
                            'user_id' => $userId
                        ],
                        ['$set' => ['privacy' => $request->privacy]]
                    );
                }


                return response([
                    'message' => 'Updated Successfully'
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Function to delete a post
        parameter: post_id
    */
    public function delete(Request $request, $id)
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
            $collection = (new Mongo())->sma_mongodb->posts;

            $userCollection = $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    'user_id' => $userId
                ]
            );

            if ($userCollection) {
                $collection->deleteMany(
                    [
                        '_id' => new \MongoDB\BSON\ObjectId($id),
                        'user_id' => $userId
                    ]
                );


                return response([
                    'message' => 'Deleted Successfully'
                ]);
            } else {
                return response([
                    'message' => 'You are not authorized to perform this action'
                ]);
            }

            return response([
                'message' => 'Post Deleted Succesfully'
            ]);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }



    /*
        Function to search a post by title
    */
    public function searchByTitle($title)
    {
        try {
            //Create a connection to mongoDB
            $collection = (new Mongo())->sma_mongodb->posts;

            $userCollection =  $collection->findOne(
                [
                    'name' => ['$regex' => $title],
                ]
            );

            return response([
                'message' => $userCollection
            ]);
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }



    /*
    Function to change the privacy of a post
    -----------------------------------
    Post privacy is set false as default
    privacy->false means PUBLIC
    privacy->true means PRIVATE
    */
    public function changePrivacy(Request $request, $id)
    {
        try {
            //Get Bearer Token
            $getToken = $request->bearerToken();

            if (!isset($getToken)) {
                return response([
                    'message' => 'Bearer token not found'
                ]);
            }

            $request->validate([
                'privacy' => 'required|boolean',
            ]);

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));
            //Get Id
            $userId = $decoded->data;

            //Create a connection to mongoDB
            $collection = (new Mongo())->sma_mongodb->posts;

            $userCollection = $collection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    'user_id' => $userId
                ]
            );

            if (!$userCollection) {
                return response([
                    'message' => 'You are not authorized to perform this action'
                ]);
            }


            $collection->updateOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    'user_id' => $userId
                ],
                ['$set' => ['privacy' => $request->privacy]]
            );

            if ($request->privacy == true) {
                return response([
                    'message' => 'Post privacy changed succesfully',
                    'status' => 'Post is private now'
                ]);
            } else {
                return response([
                    'message' => 'Post privacy changed succesfully',
                    'status' => 'Post is public now'
                ]);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
