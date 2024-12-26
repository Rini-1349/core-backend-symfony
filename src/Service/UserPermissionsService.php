<?php

namespace App\Service;

use App\Entity\User;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserPermissionsService
{
    private TagAwareCacheInterface $cache;
    private RolePermissionsService $rolePermissionsService;
    private ControllerScanner $controllerScanner;
    private ParameterBagInterface $params;

    public function __construct(ParameterBagInterface $params, TagAwareCacheInterface $cache, ControllerScanner $controllerScanner, RolePermissionsService $rolePermissionsService)
    {
        $this->params = $params;
        $this->cache = $cache;
        $this->rolePermissionsService = $rolePermissionsService;
        $this->controllerScanner = $controllerScanner;
    }

    public function loadPermissions(User $user): array
    {
        $cacheKey = "userPermissions-" . $user->getId();
        // Get user permissions
        $permissions = $this->cache->get($cacheKey, function (ItemInterface $item) use ($user): array {
            $item->tag(['userPermissions']);
            // Si l'utilisateur a le rôle ROLE_SUPERADMIN, il a tous les droits - Pas besoin de les récupérer un par un
            if (in_array('ROLE_SUPERADMIN', $user->getRoles())) {
                return [];
            }

            // Liste des rôles associés à l'utilisateur
            $roles = $user->getRoles();
            $permissions = [];

            // Parcours des rôles pour collecter les permissions positives
            foreach ($roles as $role) {
                // Récupération des autorisations positives du rôle via le service
                $roleTruePermissions = $this->rolePermissionsService->getRoleTruePermissions($role);

                foreach ($roleTruePermissions as $controllerAction => $permission) {
                    // Si une permission est déjà présente, la permission positive est prioritaire
                    if (!isset($permissions[$controllerAction]) || $permissions[$controllerAction] === false) {
                        $permissions[$controllerAction] = $permission;
                    }
                }
            }

            return $permissions;
        });

        return $permissions;
    }     

    // For example : replace "App\Controller\UserController" by "users" && "getUsers" by "usersList"
    public function formatPermissionsIntoAliases(array $permissions): array
    {
        $mode = $this->params->get('role_permissions_mode');
        $controllersAndActions = $this->controllerScanner->getControllersAndActions();
       
        $aliasesPermissionsList = [];
        foreach ($permissions as $controller => $controllerPermissions) {
            if (isset($controllersAndActions[$controller]) && isset($controllersAndActions[$controller]['alias']) && isset($controllersAndActions[$controller]['actions'])) {
                $controllerAlias = $this->rolePermissionsService->getControllerAlias($controllersAndActions, $controller);
                foreach ($controllerPermissions as $action) {
                    $actionAlias = $this->rolePermissionsService->getActionAlias($controllersAndActions[$controller]['actions'], $action, $mode);
                    $aliasesPermissionsList[$controllerAlias][] = $actionAlias;
                }
            }
        }

        return $aliasesPermissionsList;
    }    
}
