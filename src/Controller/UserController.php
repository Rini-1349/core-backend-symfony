<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\QueryParameterService;
use JMS\Serializer\SerializationContext;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;

class UserController extends AbstractController
{
    #[Route('/api/users', name: 'usersList', methods: ['GET'])]
    #[IsGranted('ROLE_USER', message: 'Vous n\'avez pas les droits suffisants pour accéder à ces données')]
    #[OA\Get(
        path: '/api/users',
        tags: ['User'],
        summary: 'Get users list',
        parameters: [
            new OA\Parameter(
                name: "page",
                description: "Page",
                in: "query",
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "limit",
                description: "results per page",
                in: "query",
                schema: new OA\Schema(type: "integer")
            ),
            new OA\Parameter(
                name: "search",
                description: "global search",
                in: "query",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "lastname",
                description: "lastname search",
                in: "query",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "firstname",
                description: "firstname search",
                in: "query",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "email",
                description: "email search",
                in: "query",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "is_verified",
                description: "is_verified search",
                in: "query",
                schema: new OA\Schema(type: "boolean")
            ),
            new OA\Parameter(
                name: "email",
                description: "email search",
                in: "query",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "orderBy",
                description: "order by a specific field",
                in: "query",
                schema: new OA\Schema(type: "string")
            ),
            new OA\Parameter(
                name: "orderDir",
                description: "ASC or DESC",
                in: "query",
                schema: new OA\Schema(type: "string")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['getUser']))
                )
            )
        ]
    )]
    public function getUsers(UserRepository $userRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, QueryParameterService $queryParameterService): JsonResponse
    {
        $cache->invalidateTags(['usersCache']);

        $defaultParameters = [
            'page' => 1,
            'limit' => 20,
            'search' => null,
            'firstname' => '',
            'lastname' => '',
            'email' => '',
            'orderBy' => 'id',
            'orderDir' => 'ASC',
            'is_verified' => null,
        ];

        $params = $queryParameterService->extractParameters($request, $defaultParameters);

        $cacheKey = sprintf(
            "getUsers-%s",
            implode('-', $params)
        );
        $usersList = $cache->get($cacheKey, function (ItemInterface $item) use ($userRepository, $params) {
            $item->tag("usersCache");
            return $userRepository->getPaginatedUsersData($params);
        });

        $jsonUsersList = $serializer->serialize($usersList, 'json', SerializationContext::create()->setGroups(['getUser']));
        return new JsonResponse($jsonUsersList, Response::HTTP_OK, [], true);
   }
}
