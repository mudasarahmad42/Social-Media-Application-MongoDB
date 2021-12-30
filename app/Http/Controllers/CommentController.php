<?php

namespace App\Http\Controllers;

use App\Notifications\CommentOnYourPost;
use MongoDB\Client as Mongo;
use Firebase\JWT\JWT;
use Firebase\JWT\Key;

use Illuminate\Http\Request;
use Throwable;

class CommentController extends Controller
{

    /*
        Function to create a comment
        parameter: post_id
    */
    public function create(Request $request, $id)
    {
        //try {
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

        //Get friends of this user
        $userCollection = (new Mongo())->sma_mongodb->users;


        //Arrays
        $friendReceivedIds = ['sender_id' => null];
        $friendSentIds = ['receiver_id' => null];

        //Users sent requests
        $sentRequests = $userCollection->find(
            [
                '_id' => new \MongoDB\BSON\ObjectId($userId),
                "sentRequests.status" => true
            ],
        );

        if (!$sentRequests->isDead()) {
            //Array of friends to whom i sent the request
            foreach ($sentRequests as $row) {
                $friendSentIds[] = $row['receiver_id'];
            }
        } else {
            $friendSentIds = ['receiver_id' => null];
        }


        //Users received requests
        $UserrecievedRequests = $userCollection->find(
            [
                '_id' => new \MongoDB\BSON\ObjectId($userId),
                "receivedRequests.status" => true
            ],
        );


        if (!$UserrecievedRequests->isDead()) {
            $recievedRequestsArray = $UserrecievedRequests->toArray();

            //Array of friends who sent me the request
            foreach ($recievedRequestsArray['receivedRequests'] as $row) {
                $friendReceivedIds[] = $row['sender_id'];
            }
        } else {
            $friendReceivedIds = ['sender_id' => null];
        }

        //connect to posts collection
        $postCollection = (new Mongo())->sma_mongodb->posts;

        //Check if posts exists
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


        //Get users
        $user = $userCollection->findOne(
            [
                '_id' => new \MongoDB\BSON\ObjectId($author),
            ]
        );

        /*
            If author of the posts is
            > User's friend
            > User is the author of the post
            > Post is public
            allow user to create comment otherwise return unauthorized response
        */
        if (in_array($author, $friendReceivedIds) || in_array($author, $friendSentIds) || $author == $userId || $getPost['privacy'] == '0') {

            $randomNumber = substr(number_format(time() * rand(), 0, '', ''), 0, 6);

            //create comment in the post
            $commentCreated = $postCollection->updateOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                ],
                [
                    '$push' => [
                        'comments' => [
                            'id' => $randomNumber,
                            "user_id" => $userId,
                            'content' => $request->content,
                        ]
                    ]
                ]
            );

            //$user->notify(new CommentOnYourPost($commentCreated));

            return response([
                'message' => 'Comment created successfully'
            ]);
        } else {
            return response([
                'message' => 'You are not allowed to comment on this post'
            ], 404);
        }
        // } catch (Throwable $e) {
        //     return response(['message' => $e->getMessage()]);
        // }
    }


    /*
        Updates comment by id
    */
    public function update(Request $request, $id, $cid)
    {
        try {
            //Get Bearer Token
            $getToken = $request->bearerToken();


            //connect to posts collection
            $postCollection = (new Mongo())->sma_mongodb->posts;

            //Get comment
            $getComment = $postCollection->findOne(
                [
                    'comment.id' => $id,
                ]
            );

            if (!$getComment) {
                return response([
                    'message' => 'Comment does not exist'
                ]);
            }

            if (!isset($getToken)) {
                return response([
                    'message' => 'Bearer token not found'
                ]);
            }

            if ($request->content == null) {
                return response([
                    'message' => 'content is required'
                ]);
            }

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));

            //Get Id
            $userId = $decoded->data;

            //Get friends of this user
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

            //Check if posts exists
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

            //user_id of commenter
            $commenter = $getComment['user_id'];

            /*
            If author of the posts is
            > User's friend
            > User is the author of the post
            > Post is public
            allow user to delete comment otherwise return unauthorized response
        */

            if ((in_array($author, $friendReceivedIds) || in_array($author, $friendSentIds) || $author == $userId || $getPost['privacy'] == '0') || $commenter == $userId) {

                $comment = $postCollection->findOne([
                    '_id' => new \MongoDB\BSON\ObjectId($id),
                    "comments.id" => $cid
                ]);


                if ($comment) {

                    $commentCollection = $postCollection->updateOne(
                        [
                            '_id' => new \MongoDB\BSON\ObjectId($id),
                            "comments.id" => $cid
                        ],
                        [
                            '$set' => ['comments.$.content' => $request->content]
                        ]
                    );

                    return response([
                        'message' => 'Comment updated successfully'
                    ], 404);
                } else {
                    return response([
                        'message' => 'Something went wrong'
                    ], 404);
                }
            } else {
                return response([
                    'message' => 'You are not allowed to update this comment'
                ], 404);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Deletes comment by id
    */
    public function delete(Request $request, $pid, $cid)
    {
        try {
            //Get Bearer Token
            $getToken = $request->bearerToken();

            //connect to posts collection
            $postCollection = (new Mongo())->sma_mongodb->posts;

            //Get comment
            $getComment = $postCollection->findOne([
                '_id' => new \MongoDB\BSON\ObjectId($pid),
                "comments.id" => $cid
            ]);

            if (!$getComment) {
                return response([
                    'message' => 'Comment does not exist'
                ]);
            }

            if (!isset($getToken)) {
                return response([
                    'message' => 'Bearer token not found'
                ]);
            }

            //Decode
            $decoded = JWT::decode($getToken, new Key('ProgrammersForce', 'HS256'));

            //Get Id
            $userId = $decoded->data;

            //Get Post id
            $postId = $getComment['post_id'];

            //Check if request has been received
            $getPost = $postCollection->findOne(
                [
                    '_id' => new \MongoDB\BSON\ObjectId($postId),
                ]
            );

            //user_id of author of this post
            $author = $getPost['user_id'];


            //user_id of commenter
            $commenter = $getComment['user_id'];


            /*
            If comment is of
            > User's
            > Post is of user
            > Post is public
            allow user to delete comment otherwise return unauthorized response
        */
            if ($author == $userId || $getPost['privacy'] == '0' || $commenter == $userId) {

                $comment = $postCollection->findOne([
                    '_id' => new \MongoDB\BSON\ObjectId($postId),
                    "comments.id" => $cid
                ]);

                if ($comment) {

                    //create comment in the post
                    $commentCreated = $postCollection->updateOne(
                        [
                            '_id' => new \MongoDB\BSON\ObjectId($pid),
                            "comments.id" => $cid
                        ],
                        [
                            '$pull' => [
                                'comments' => [
                                    'id' => $cid
                                ]
                            ]
                        ]
                    );

                    return response([
                        'message' => 'Comment deleted successfully',
                        'comment' => $comment
                    ]);
                } else {
                    return response([
                        'message' => 'You are not allowed to comment on this post'
                    ], 404);
                }
            } else {
                return response([
                    'message' => 'You are not allowed to comment on this post'
                ], 404);
            }
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }


    /*
        Returns user's comments
    */
    public function myComments(Request $request)
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

            $postCollection = (new Mongo())->sma_mongodb->posts;

            //Get comment
            $comments = $postCollection->findOne([
                "comments.user_id" => $userId
            ]);

            return $comments;
        } catch (Throwable $e) {
            return response(['message' => $e->getMessage()]);
        }
    }
}
