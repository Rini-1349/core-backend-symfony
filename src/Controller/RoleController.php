<?php

namespace App\Controller;

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
use App\Attribute\ControllerMetadata;
use App\Attribute\ActionMetadata;
use App\Attribute\AccessMethods;
use App\Entity\Role;
use App\Entity\User;
use App\Repository\RoleRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;

#[ControllerMetadata(alias: "roles", description: 'Rôles')]
#[AccessMethods(
    readMethods: ['getRoles', 'getRoleDetails'],
    writeMethods: ['createRole', 'updateRole']
)]
class RoleController extends AbstractController
{
    #[Route('/api/roles', name: 'getRoles', methods: ['GET'])]
    #[ActionMetadata(alias: "rolesList", description: 'Liste rôles')]
    #[OA\Get(
        path: '/api/roles',
        tags: ['Role'],
        summary: 'Get roles list',
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
                        ),
                        new OA\Property(
                            property: 'data',
                            type: 'array',
                            items: new OA\Items(ref: new Model(type: Role::class, groups: ['getRole']))
                        )
                    ]
                )
            )
        ]
    )]
    public function getRoles(RoleRepository $roleRepository, SerializerInterface $serializer, Request $request, TagAwareCacheInterface $cache, QueryParameterService $queryParameterService, Security $security): JsonResponse
    {
        $cache->invalidateTags(['rolesCache']);

        $defaultParameters = [
            'page' => 1,
            'limit' => 20,
            'search' => null,
            'orderBy' => 'id',
            'orderDir' => 'ASC',
        ];

        /** @var User $user */
        $user = $security->getUser();
        $params = $queryParameterService->extractParameters($request, $defaultParameters);

        $cacheKey = sprintf(
            "getRoles-%s",
            implode('-', $params)
        ) . '-' . $user->getId();
        $rolesList = $cache->get($cacheKey, function (ItemInterface $item) use ($roleRepository, $params) {
            $item->tag("rolesCache");
            return $roleRepository->getPaginatedRolesData($params);
        });

        $jsonRolesList = $serializer->serialize($rolesList, 'json', SerializationContext::create()->setGroups(['getRole']));

        $responseContent = [
            'message' => "Liste des rôles récupérée.",
            'data' => json_decode($jsonRolesList, true)
        ];

        return new JsonResponse(json_encode($responseContent), Response::HTTP_OK, [], true);
    }

    #[Route('/api/roles/{id}', name: 'getRoleDetails', methods: ['GET'])]
    #[ActionMetadata(alias: "roleDetails", description: 'Détails rôle')]
    #[OA\Get(
        path: '/api/roles/{id}',
        tags: ['Role'],
        summary: 'Get role',
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
                            ref: new Model(type: Role::class, groups: ['getRole'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'Role id to get',
        in: 'path',
    )]
    public function getRoleDetails(Role $role, SerializerInterface $serializer): JsonResponse
    {
        if ($role->getId() === 'ROLE_SUPERADMIN') {
            return new JsonResponse(
                $serializer->serialize(['message' => "Opération interdite sur ce rôle."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $jsonRole = $serializer->serialize($role, 'json', SerializationContext::create()->setGroups(['getRole']));
            
        $responseContent = [
            'message' => "Informations du rôle récupérées.",
            'data' => json_decode($jsonRole, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }
   

    #[Route('/api/roles', name: 'createRole', methods: ['POST'])]
    #[ActionMetadata(alias: "newRole", description: 'Créer rôle')]
    #[OA\Post(
        path: '/api/roles',
        tags: ['Role'],
        summary: 'Creates a new role',
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
                            ref: new Model(type: Role::class, groups: ['getRole'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: Role::class, groups: ['getRole']))
    )]
    public function createRole(Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        $data = json_decode($request->getContent(), true);

        // Formatage id pour avoir une forme ROLE_ID
        $slugger = new AsciiSlugger();
        $id = $slugger->slug($data['id'])->toString();
        $id = str_replace('-', '_', $id);
        $id = "ROLE_" . strtoupper($id);

        $role = new Role();
        $role->setId($id);
        $role->setDescription($data['description']);

        $errors = $validator->validate($role);
        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors[0], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }
        
        $em->persist($role);
        $em->flush();
        $cache->invalidateTags(['rolesCache']);

        $jsonRole = $serializer->serialize($role, 'json', SerializationContext::create()->setGroups(['getRole']));
            
        $responseContent = [
            'message' => "Rôle ajouté avec succès.",
            'data' => json_decode($jsonRole, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_CREATED);
    }


    #[Route('/api/roles/{id}', name: 'updateRole', methods: ['PUT'])]
    #[ActionMetadata(alias: "roleEdit", description: 'Modifier rôle')]
    #[OA\Put(
        path: '/api/roles/{id}',
        tags: ['Role'],
        summary: 'Update role',
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
                            ref: new Model(type: Role::class, groups: ['getRole'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'Role id to update',
        in: 'path',
    )]
    #[OA\RequestBody(
        required : true,
        content : new OA\JsonContent(ref: new Model(type: Role::class, groups: ['getRoleDescription']))
    )]
    public function updateRole(Role $currentRole, Request $request, SerializerInterface $serializer, EntityManagerInterface $em, ValidatorInterface $validator, TagAwareCacheInterface $cache): JsonResponse
    {
        if ($currentRole->getId() === 'ROLE_SUPERADMIN') {
            return new JsonResponse(
                $serializer->serialize(['message' => "Opération interdite sur ce rôle."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $updatedRole = $serializer->deserialize(
            $request->getContent(), 
            Role::class, 
            'json'
        );

        $currentRole->setDescription($updatedRole->getDescription());

        $errors = $validator->validate($currentRole);
        if ($errors->count() > 0) {
            return new JsonResponse(
                $serializer->serialize($errors, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $em->persist($currentRole);
        $em->flush();
        $cache->invalidateTags(['rolesCache']);

        $jsonRole = $serializer->serialize($currentRole, 'json', SerializationContext::create()->setGroups(['getRole']));
        
        $responseContent = [
            'message' => "Rôle modifié avec succès.",
            'data' => json_decode($jsonRole, true),
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }
}
