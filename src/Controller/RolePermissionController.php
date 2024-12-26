<?php

namespace App\Controller;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use JMS\Serializer\SerializerInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Nelmio\ApiDocBundle\Annotation\Model;
use OpenApi\Attributes as OA;
use App\Attribute\ControllerMetadata;
use App\Attribute\ActionMetadata;
use App\Attribute\AccessMethods;
use App\Entity\Role;
use App\Entity\RolePermission;
use App\Repository\RolePermissionRepository;
use App\Service\ControllerScanner;
use App\Service\RolePermissionsService;

#[ControllerMetadata(alias: "rolePermissions", description: 'Permissions Rôle')]
#[AccessMethods(
    readMethods: ['getRolePermissions'],
    writeMethods: ['updateRolePermissions']
)]
class RolePermissionController extends AbstractController
{
    #[Route('/api/roles/{id}/permissions', name: 'getRolePermissions', methods: ['GET'])]
    #[ActionMetadata(alias: "rolePermissionsList", description: 'Liste permissions rôles')]
    #[OA\Get(
        path: '/api/roles/{id}/permissions',
        tags: ['RolePermission'],
        summary: 'Get role permissions list',
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
                            ref: new Model(type: Role::class, groups: ['getRolePermissions'])
                        )
                    ]
                )
            )
        ]
    )]
    #[OA\Parameter(
        name: 'id',
        description: 'Role id to get permissions',
        in: 'path',
    )]
    public function getRolePermissions(Role $role, RolePermissionsService $rolePermissionsService, TagAwareCacheInterface $cache): JsonResponse
    {
        // Récupère les permissions true d'un rôle sous forme [Controller1 => [0 => action1, 1 => action2, etc.], Controller2 => [etc.]]
        $roleTruePermissions = $rolePermissionsService->getRoleTruePermissions($role->getId());
        // - En mode "actions" : [Controller => ['description' => '', 'permissions' => [action1, action2, etc.]]
        // - En mode "read-write" : [Controller => ['description' => '', 'actions'  => [], 'read' => ['is_authoried' => true], 'write' => ['is_authorized' => true]]]
        $rolePermissionsByControllers = $rolePermissionsService->getRolePermissionsByControllers($role->getId(), $roleTruePermissions);
        // - En mode "actions" : [ControllerAlias => ['description' => '', 'actions' => [action1Alias => ['is_authorized' => true/false 'description' => ''], etc.]]
        // - En mode "read-write" : [ControllerAlias => ['description' => '', 'actions'  => ['read' => ['is_authorized' => true], 'write' => ['is_authorized' => false]]]
        $aliasesRolePermissions = $rolePermissionsService->formatControllersAndActionsIntoAliases($rolePermissionsByControllers);

        $responseContent = [
            'message' => "Liste des permissions du rôle récupérée.",
            'data' => ['role' => $role, 'rolePermissions' => $aliasesRolePermissions],
        ];

        return new JsonResponse(json_encode($responseContent), Response::HTTP_OK, [], true);
    }

    #[Route('/api/roles/{id}/permissions', name: 'updateRolePermissions', methods: ['POST'])]
    #[ActionMetadata(alias: "rolePermissionsEdit", description: 'Modifier permissions rôle')]
    #[OA\Post(
        path: '/api/roles/{id}/permissions',
        tags: ['RolePermission'],
        summary: 'Update role permissions',
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
        description: 'Role id to update permissions',
        in: 'path',
    )]
    #[OA\RequestBody(
        required: true,
        content: new OA\JsonContent(
            type: 'object',
            description: 'Un objet où chaque clé est un contrôleur (Ex: "App\\Controller\\UserController"), et chaque valeur est un objet contenant des actions (Ex: "getUsers") ou des modes ("read", "write") en tant que clés avec des booléens comme valeurs.',
            additionalProperties: new OA\AdditionalProperties(
                type: 'object',
                additionalProperties: new OA\AdditionalProperties(
                    type: 'boolean'
                )
            ),
            example: '{"*actions mode expectations:*users":{"usersList":true, "removeUser":false},"*read-write mode expectations:*users":{"read":true,"write":false}}'
        )
    )]
    public function updateRolePermissions(Role $role, Request $request, SerializerInterface $serializer, RolePermissionsService $rolePermissionsService, RolePermissionRepository $rolePermissionRepository, ControllerScanner $controllerScanner, TagAwareCacheInterface $cache): JsonResponse
    {
        if ($role->getId() === 'ROLE_SUPERADMIN') {
            return new JsonResponse(
                $serializer->serialize(['message' => "Opération interdite sur ce rôle."], 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        $data = $serializer->deserialize($request->getContent(), 'array', 'json');

        if ($errorMessage = $rolePermissionsService->hasRolePermissionsDataFormatError($data)) {
            return new JsonResponse(
                $serializer->serialize($errorMessage, 'json'),
                JsonResponse::HTTP_BAD_REQUEST,
                [],
                true
            );
        }

        // Récupérer toutes les permissions existantes pour ce rôle
        $existingPermissions = $rolePermissionRepository->findBy(['role' => $role->getId()]);

        // Indexer les permissions existantes par contrôleur et action pour un accès rapide
        $existingPermissionsByControllerAndAction = [];
        foreach ($existingPermissions as $permission) {
            $existingPermissionsByControllerAndAction[$permission->getController()][$permission->getAction()] = $permission;
        }
    
        // Préparer les entités à persister
        $entityManager = $rolePermissionRepository->getEntityManager();

        // Récupérer la liste des controllers et actions
        $controllersAndActions = $controllerScanner->getControllersAndActions();
        $usedPermissions = [];
        
        // Replace controllers and actions aliases (ex: "users" becomes "App\Controller\UserController")
        $formattedDataFromAliases = $rolePermissionsService->formatDataFromAliases($data, $controllersAndActions);

        foreach ($formattedDataFromAliases as $controller => $controllerData) {
            $controllerPermissionsByActions = $rolePermissionsService->getControllerPermissionsByActions($controllerData, $controller, $controllersAndActions);
    
            foreach ($controllerPermissionsByActions as $action => $isAuthorized) {
                $usedPermissions[$controller][$action] = true;
                if (isset($existingPermissionsByControllerAndAction[$controller][$action])) {
                    // Mettre à jour l'autorisation existante si nécessaire
                    $permission = $existingPermissionsByControllerAndAction[$controller][$action];
                    if ($permission->getIsAuthorized() !== $isAuthorized) {
                        $permission->setIsAuthorized($isAuthorized);
                        $entityManager->persist($permission);
                    }
                } else {
                    // Créer une nouvelle autorisation
                    $permission = new RolePermission();
                    $permission->setRoleDetails($role);
                    $permission->setController($controller);
                    $permission->setAction($action);
                    $permission->setIsAuthorized($isAuthorized);
                    $entityManager->persist($permission);
                }
            }
        }

        // Identifier et supprimer les permissions non utilisées
        foreach ($existingPermissionsByControllerAndAction as $controller => $actions) {
            foreach ($actions as $action => $permission) {
                if (!isset($usedPermissions[$controller][$action])) {
                    $entityManager->remove($permission);
                }
            }
        }

        // Sauvegarder toutes les modifications en une seule fois
        $entityManager->flush();

        // Vider le cache 
        $cache->invalidateTags(['rolePermissionsCache']);

        $responseContent = [
            'message' => "Permissions du rôle modifiées avec succès.",
            'data' => $role,
        ];

        return JsonResponse::fromJsonString(json_encode($responseContent))->setStatusCode(Response::HTTP_OK);
    }
}
