<?php

namespace AppBundle\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\Controller;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class WalletController extends Controller
{

    const DEFAULT_SIZE = 30;
    const DEFAULT_CACHING = 4;

    /**
     * @Route("/wallet/messages", name="messages")
     */
    public function messagesByHandlerAction(Request $request)
    {
        $size = (int)$request->get('size', self::DEFAULT_SIZE);
        $scope = $request->get('scope');
        $handler = $request->get('handler');
        $items = [];
        $cursor = $this->getDB()->Transactions
            ->aggregate([
                [
                    '$lookup' =>
                        [
                            'from' => 'Messages',
                            'localField' => 'transaction_id',
                            'foreignField' => 'transaction_id',
                            'as' => 'message',
                        ],
                ]
                ,
                [
                    '$match' => [
                        '$and' => [
                            ["scope" => $scope],
                            ["message.handler_account_name" => $handler],
                        ],
                    ],
                ],
                ['$unwind' => '$message'],
                ['$sort' => ['createdAt' => -1]],
                ['$skip' => (int)$request->get('page', 0) * $size],
                ['$limit' => $size],
            ]);

        foreach ($cursor as $key => $document) {
            $items[] = $document;
        }

        return new JsonResponse($items);
    }

    /**
     * @Route("/wallet/messages/groups", name="messages_count")
     */
    public function groupMessagesByHandlerAction(Request $request)
    {
        $scope = $request->get('scope');
        $items = [];
        $cursor = $this->getDB()->Transactions
            ->aggregate([
                [
                    '$lookup' =>
                        [
                            'from' => 'Messages',
                            'localField' => 'transaction_id',
                            'foreignField' => 'transaction_id',
                            'as' => 'messages',
                        ],
                ]
                ,
                ['$match' => ["scope" => $scope]],
                ['$unwind' => '$messages'],
                [
                    '$group' => [
                        '_id' => '$messages.handler_account_name',
                        'count' => ['$sum' => 1],
                    ],
                ],
            ]);

        foreach ($cursor as $key => $document) {
            $items[] = $document;
        }

        return new JsonResponse($items);
    }


    private function getDB()
    {
        $mongo = new \MongoClient($this->getParameter('mongodb_server'));

        return $mongo->selectDB($this->getParameter('db_name'));
    }
}