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
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use Symfony\Component\Validator\Validator\ValidatorInterface;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use App\Attribute\ControllerMetadata;
use App\Attribute\ActionMetadata;
use App\Attribute\AccessMethods;
use App\Repository\RoleRepository;
use App\Service\UserService;
use Symfony\Bundle\SecurityBundle\Security;

#[ControllerMetadata(alias: "users", description: 'Utilisateurs')]
#[AccessMethods(
    readMethods: ['getUsers', 'getUserDetails', 'getRolesListForUsers'],
    writeMethods: ['createUser', 'updateUser', 'editUserPassword', 'deleteUser']
)]
class UserController extends AbstractController
{
    #[Route('/api/users/roles', name: 'getRolesListForUsers', methods: ['GET'])]
    #[ActionMetadata(alias: "rolesListForUsers", description: 'Liste rôles à associer à utilisateur')]
    #[OA\Get(
        path: '/api/users/roles',
        tags: ['User'],
        summary: 'Get roles list',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent(
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'User list retrieved successfully'
                        ),
                        new OA\Property(
                            property: "roles",
                            type: "array",
                            items: new OA\Items(type: "string"),
                            example: ["ROLE_ADMIN" => "Administrateur", "ROLE_USER" => "Utilisateur"]
                        )
                    ]
                )
            )
        ]
    )]
    public function getRolesListForUsers(RoleRepository $roleRepository): JsonResponse
    {
        $responseContent = [
            'message' => "Liste des rôles récupérée.",
            'data' => $roleRepository->findForSelect()
        ];

        return new JsonResponse(json_encode($responseContent), Response::HTTP_OK, [], true);
    }


    #[Route('/api/users', name: 'getUsers', methods: ['GET'])]
    #[ActionMetadata(alias: "usersList", description: 'Liste utilisateurs')]
    #[OA\Get(
        path: '/api/users',
        tags: ['User'],
        summary: 'Get users list',
        parameters: [
            new OA\Parameter(
                name: "get_roles",
                description: "define if we need to get roles list or not",
                in: "query",
                schema: new OA\Schema(type: "integer")
            ),
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
                name: "is_verified",
                description: "is_verified search",
                in: "query",
                schema: new OA\Schema(type: "boolean")
            ),
            new OA\Parameter(
                name: "roles_descriptions",
                description: "role_id search",
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
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                            example: 'User list retrieved successfully'
                        ),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "users",
                                    type: "array",
                                    items: new OA\Items(ref: new Model(type: User::class, groups: ["getUser"]))
                                ),
                                new OA\Property(
                                    property: "roles",
                                    type: "array",
                                    items: new OA\Items(type: "string"),
                                    example: ["ROLE_ADMIN" => "Administrateur", "ROLE_USER" => "Utilisateur"]
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    public function getUsers(UserRepository $userRepository, RoleRepository $roleRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, QueryParameterService $queryParameterService, Security $security): JsonResponse
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
            'roles_descriptions' => null,
            'get_roles' => false
        ];

        /** @var User $user */
        $user = $security->getUser();
        $params = $queryParameterService->extractParameters($request, $defaultParameters);

        $cacheKey = sprintf(
            "getUsers-%s",
            implode('-', $params)
        ) . '-' . $user->getId();
        $usersList = $cache->get($cacheKey, function (ItemInterface $item) use ($userRepository, $params) {
            $item->tag("usersCache");
            return $userRepository->getPaginatedUsersData($params);
        });

        $jsonUsersList = $serializer->serialize($usersList, 'json', SerializationContext::create()->setGroups(['getUser']));

        $responseContent = [
            'message' => "Liste des utilisateurs récupérée.",
            'data' => [
                'users' => json_decode($jsonUsersList, true),
                'roles' => $params['get_roles'] ? $roleRepository->findForSelect() : []
            ],
        ];

        return new JsonResponse(json_encode($responseContent), Response::HTTP_OK, [], true);
    }

    #[Route('/api/users/{id}', name: 'getUserDetails', methods: ['GET'])]
    #[ActionMetadata(alias: "userDetails", description: 'Détails utilisateur')]
    #[OA\Get(
        path: '/api/users/{id}',
        tags: ['User'],
        summary: 'Get user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent( 
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                        ),
                        new OA\Property(
                            property: "data",
                            type: "object",
                            properties: [
                                new OA\Property(
                                    property: "users",
                                    type: "array",
                                    items: new OA\Items(ref: new Model(type: User::class, groups: ["getUser", 'getUserRoles']))
                                ),
                                new OA\Property(
                                    property: "roles",
                                    type: "array",
                                    items: new OA\Items(type: "string"),
                                    example: ["ROLE_ADMIN" => "Administrateur", "ROLE_USER" => "Utilisateur"]
                                )
                            ]
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'User id to get',
        in: 'path',
    )]
    public function getUserDetails(User $user, RoleRepository $roleRepository, SerializerInterface $serializer): JsonResponse
    {
        if (in_array('ROLE_SUPERADMIN', $user->getRoles(), true)) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Opération interdite sur cet utilisateur."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser', 'getUserRoles']));
            
        $responseContent = [
            'message' => "Informations de l'utilisateur récupérées.",
            'data' => [
                'user' => json_decode($jsonUser, true),
                'roles' => $roleRepository->findForSelect()
            ],
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }
   

    #[Route('/api/users', name: 'createUser', methods: ['POST'])]
    #[ActionMetadata(alias: "newUser", description: 'Créer utilisateur')]
    #[OA\Post(
        path: '/api/users',
        tags: ['User'],
        summary: 'Creates a new user',
        responses: [
            new OA\Response(
                response: 201,
                description: 'Created',
                content: new OA\JsonContent( 
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            ref: new Model(type: User::class, groups: ['getUser', 'getUserRoles'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'lastname', type: 'string'),
                new OA\Property(property: 'firstname', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'password', type: 'string'),
                new OA\Property(property: 'isVerified', type: 'boolean'),
            ]
        )
    )]
    public function createUser(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher, TagAwareCacheInterface $cache, RoleRepository $roleRepository, UserService $userService): JsonResponse
    {
        $data = json_decode($request->getContent(), true);
        if (!isset($data['roles'])) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Vous devez renseigner au moins un rôle pour cet utilisateur."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user = new User();

        if (isset($data['roles']) && is_array($data['roles'])) {
            $user->setRoles($data['roles']);
        } else {
            $user->setRoles([$data['roles']]);
        }

        foreach (['lastname', 'firstname', 'email', 'is_verified' => 'isVerified', 'password'] as $camelCaseField => $field) {
            $setter = 'set' . ucfirst($field);
            $user->$setter($data[is_string($camelCaseField) ? $camelCaseField : $field]);
        }

        $user->setPassword($passwordHasher->hashPassword($user, $user->getPassword()));
    
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

        $rolesDescriptions = $userService->getRolesDescriptions($user, $roleRepository->getIndexedRoles($user->getRoles()));
        $user->setRolesDescriptions($rolesDescriptions);
        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser', 'getUserRoles']));
            
        $responseContent = [
            'message' => "Utilisateur ajouté avec succès.",
            'data' => json_decode($jsonUser, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_CREATED);
    }


    #[Route('/api/users/{id}', name: 'updateUser', methods: ['PUT'])]
    #[ActionMetadata(alias: "userEdit", description: 'Modifier utilisateur')]
    #[OA\Put(
        path: '/api/users/{id}',
        tags: ['User'],
        summary: 'Update user',
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent( 
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            ref: new Model(type: User::class, groups: ['getUser', 'getUserRoles'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'User id to update',
        in: 'path',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            properties: [
                new OA\Property(property: 'roles', type: 'array', items: new OA\Items(type: 'string')),
                new OA\Property(property: 'lastname', type: 'string'),
                new OA\Property(property: 'firstname', type: 'string'),
                new OA\Property(property: 'email', type: 'string'),
                new OA\Property(property: 'isVerified', type: 'boolean'),
            ]
        )
    )]
    public function updateUser(User $currentUser, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache, RoleRepository $roleRepository, UserService $userService): JsonResponse
    {
        if (in_array('ROLE_SUPERADMIN', $currentUser->getRoles(), true)) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Opération interdite sur cet utilisateur."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $data = json_decode($request->getContent(), true);
        if (!isset($data['roles'])) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Vous devez renseigner au moins un rôle pour cet utilisateur."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        if (is_array($data['roles'])) {
            $currentUser->setRoles($data['roles']);
        } else {
            $currentUser->setRoles([$data['roles']]);
        }

        foreach (['lastname', 'firstname', 'email', 'is_verified' => 'isVerified'] as $camelCaseField => $field) {
            $setter = 'set' . ucfirst($field);
            $currentUser->$setter($data[is_string($camelCaseField) ? $camelCaseField : $field]);
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

        $rolesDescriptions = $userService->getRolesDescriptions($currentUser, $roleRepository->getIndexedRoles($currentUser->getRoles()));
        $currentUser->setRolesDescriptions($rolesDescriptions);
        $jsonUser = $serializer->serialize($currentUser, 'json', SerializationContext::create()->setGroups(['getUser', 'getUserRoles']));
        
        $responseContent = [
            'message' => "Utilisateur modifié avec succès.",
            'data' => json_decode($jsonUser, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }


    #[Route('/api/users/{id}/edit-password', name: 'editUserPassword', methods: ['POST'])]
    #[ActionMetadata(alias: "userEditPassword", description: 'Modifier mot de passe utilisateur')]
    #[OA\Post(
        path: '/api/users/{id}/edit-password',
        tags: ['User'],
        summary: 'Edit user password',
        parameters: [
            new OA\Parameter(
                name: "id",
                description: "User ID",
                in: "path",
                required: true,
                schema: new OA\Schema(type: "integer")
            ),
        ],
        responses: [
            new OA\Response(
                response: 200,
                description: 'OK',
                content: new OA\JsonContent( 
                    type: 'object',
                    properties: [
                        new OA\Property(
                            property: 'message',
                            type: 'string',
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'object',
                            ref: new Model(type: User::class, groups: ['getUser'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: User::class, groups: ['password']))
    )]
    public function editUserPassword(User $user, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, UserPasswordHasherInterface $passwordHasher): Response
    {
        if (in_array('ROLE_SUPERADMIN', $user->getRoles(), true)) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Opération interdite sur cet utilisateur."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $content = $serializer->deserialize($request->getContent(), 'array', 'json');

        if (!isset($content['password'])) {
            return new JsonResponse(
                $serializer->serialize(['message' => 'Mot de passe obligatoire'], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $user->setPassword($passwordHasher->hashPassword($user, $content['password']));

        $errors = $validator->validate($user);

        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors[0], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->persist($user);
        $em->flush();

        $jsonUser = $serializer->serialize($user, 'json', SerializationContext::create()->setGroups(['getUser']));
        
        $responseContent = [
            'message' => "Mot de passe modifié avec succès.",
            'data' => json_decode($jsonUser, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }


    #[Route('/api/users/{id}', name: 'deleteUser', methods: ['DELETE'])]
    #[ActionMetadata(alias: "removeUser", description: 'Supprimer utilisateur')]
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
    public function deleteUser(User $user, EntityManagerInterface $em, SerializerInterface $serializer, TagAwareCacheInterface $cache): JsonResponse
    {
        if (in_array('ROLE_SUPERADMIN', $user->getRoles(), true)) {
            return new JsonResponse(
                $serializer->serialize(['message' => "Opération interdite sur cet utilisateur."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }
        
        $cache->invalidateTags(['usersCache']);
        $em->remove($user);
        $em->flush();
        
        return new JsonResponse(null, Response::HTTP_NO_CONTENT);
    }
}
