<?php

namespace App\Controller;

use App\Entity\User;
use App\Repository\UserRepository;
use App\Service\QueryParameterService;
use Doctrine\ORM\EntityManagerInterface;
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
use Symfony\Component\PasswordHasher\Hasher\PasswordHasherFactoryInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;

class UserController extends AbstractController
{
    #[Route('/api/profile', name: 'getProfile', methods: ['GET'])]
    #[OA\Get(
        path: '/api/profile',
        tags: ['User'],
        summary: 'Get profile',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK'
            )
        ]
    )]
    public function getProfile(SecurityBundleSecurity $security, SerializerInterface $serializer): JsonResponse
    {
        $user = $security?->getUser();
        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['profile']));
            
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }


    #[Route('/api/profile', name: 'updateProfile', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/profile',
        tags: ['User'],
        summary: 'Update profile',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK'
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['profile']))
    )]
    public function updateProfile(SecurityBundleSecurity $security, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $currentUser = $security?->getUser();

        $updatedUser = $serializer->deserialize(
            $request->getContent(), 
            User::class, 
            'json'
        );

        foreach (['lastname', 'firstname', 'email'] as $field) {
            $setter = 'set' . ucfirst($field);
            $getter = 'get' . ucfirst($field);
            $currentUser->$setter($updatedUser->$getter());
        }

        $errors = $validator->validate($currentUser);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->persist($currentUser);
        $em->flush();
        $cache->invalidateTags(['usersCache']);

        $jsonUser = $serializer->serialize($currentUser, 'json', SerializationContext::create()->setGroups(['profile']));
        
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }


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

    #[Route('/api/users/{id}', name: 'getUser', methods: ['GET'])]
    #[OA\Get(
        path: '/api/users/{id}',
        tags: ['User'],
        summary: 'Get user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK'
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'User id to get',
        in: 'path',
    )]
    public function getUserDetails(User $user, SerializerInterface $serializer): JsonResponse
    {
        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
            
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }
   

    #[Route('/api/users', name: 'createUser', methods: ['POST'])]
    #[OA\Post(
        path: '/api/users',
        tags: ['User'],
        summary: 'Creates a new user',
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new OA\JsonContent(
                    type: 'array',
                    items: new OA\Items(ref: new Model(type: User::class, groups: ['getUser']))
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['createUser', 'password']))
    )]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, PasswordHasherFactoryInterface $passwordHasherFactory, TagAwareCacheInterface $cache): JsonResponse
    {
        $user = $serializer->deserialize($request->getContent(), User::class, 'json');

        $user->setRoles(['ROLE_USER']);
        $user->setPassword($passwordHasherFactory->getPasswordHasher(User::class)->hash($user->getPassword()));
    
        // Persist here to create a "createdAt" value
        $em->persist($user);
    
        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors[0], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->flush();
        $cache->invalidateTags(['usersCache']);

        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
            
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_CREATED);
    }


    #[Route('/api/users/{id}', name: 'updateUser', methods: ['PUT'])]
    #[OA\Put(
        path: '/api/users/{id}',
        tags: ['User'],
        summary: 'Update user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK'
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'User id to update',
        in: 'path',
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['updateUser']))
    )]
    public function updateUser(User $currentUser, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $updatedUser = $serializer->deserialize(
            $request->getContent(), 
            User::class, 
            'json'
        );

        foreach (['lastname', 'firstname', 'email', 'isVerified'] as $field) {
            $setter = 'set' . ucfirst($field);
            $getter = 'get' . ucfirst($field);
            $currentUser->$setter($updatedUser->$getter());
        }

        $errors = $validator->validate($currentUser);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->persist($currentUser);
        $em->flush();
        $cache->invalidateTags(['usersCache']);

        $jsonUser = $serializer->serialize($currentUser, 'json', SerializationContext::create()->setGroups(['getUser']));
        
        return JsonResponse::fromJsonString($jsonUser)->setStatusCode(Response::HTTP_OK);
    }



    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[OA\Delete(
        path: '/api/users/{id}',
        tags: ['User'],
        summary: 'Delete user',
        responses: [
            new OA\Response(
                response: 204,
                description: 'No content'
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'User id to delete',
        in: 'path',
    )]
    public function deleteUser(User $user, EntityManagerInterface $em, TagAwareCacheInterface $cache): JsonResponse
    {

        $cache->invalidateTags(['usersCache']);
        $em->remove($user);
        $em->flush();
        
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
