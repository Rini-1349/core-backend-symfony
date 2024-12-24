<?php 

namespace App\Service;

use App\Entity\RolePermission;
use App\Repository\RolePermissionRepository;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class RolePermissionsService
{
    private ParameterBagInterface $params;
    private TagAwareCacheInterface $cache;
    private ControllerScanner $controllerScanner;
    private RolePermissionRepository $rolePermissionRepository;

    public function __construct(ParameterBagInterface $params, TagAwareCacheInterface $cache, ControllerScanner $controllerScanner, RolePermissionRepository $rolePermissionRepository)
    {
        $this->params = $params;
        $this->cache = $cache;
        $this->controllerScanner = $controllerScanner;
        $this->rolePermissionRepository = $rolePermissionRepository;
    }

    /* Récupère les permissions true d'un rôle sous forme [Controller1 => [0 => action1, 1 => action2, etc.], Controller2 => [etc.]] */
    public function getRoleTruePermissions($roleId): array 
    {

        $cacheKey = "role-" . $roleId;
        $rolePermissionsList = $this->cache->get($cacheKey, function (ItemInterface $item) use ($roleId) {
            $item->tag("rolePermissionsCache");
            return $this->rolePermissionRepository->findBy(['role' => $roleId, 'isAuthorized' => true]);
        });

        $roleTruePermissions = [];

        foreach ($rolePermissionsList as $rolePermission) {
            $controller = $rolePermission->getController();
            $action = $rolePermission->getAction();
            if (isset($roleTruePermissions[$controller])) {
                array_push($roleTruePermissions[$controller], $action);
            } else {
                $roleTruePermissions[$controller] = [$action];
            }
        }

        return $roleTruePermissions;
    }

    /* Récupère (dans le cache si possible) la liste des Controllers et des read/write ou actions (selon le mode) autorisés, sous forme :
        - En mode "actions" : [Controller => ['description' => '', 'permissions' => [action1, action2, etc.]]
        - En mode "read-write" : [Controller => ['description' => '', 'read' => ['is_authoried' => true], 'write' => ['is_authorized' => true]]]
    */
    public function getRolePermissionsByControllers(string $roleId, array $roleTruePermissions): array
    {
        $mode = $this->params->get('role_permissions_mode');
        $cacheKey = "getRolePermissions-" . $mode . '-' . $roleId;
        $rolePermissionsByControllers = $this->cache->get($cacheKey, function (ItemInterface $item) use ($roleTruePermissions, $mode) {
            $item->tag("rolePermissionsCache");

            $rolePermissionsByControllers = $controllersAndActions = $this->controllerScanner->getControllersAndActions();

            foreach ($controllersAndActions as $controllerName => $controllerArray) {
                if (isset($roleTruePermissions[$controllerName])) {
                    if (!is_null($mode) && $mode !== 'read-write') {
                        $rolePermissionsByControllers[$controllerName]['permissions'] = $roleTruePermissions[$controllerName];
                    } else {
                        // Vérifie les actions de lecture et d'écriture
                        foreach (['read', 'write'] as $accessMethod) {
                            $mayAccess = false;
                            foreach ($controllerArray['actions'][$accessMethod] as $action) {
                                $mayAccess = $mayAccess || in_array($action['action'], $roleTruePermissions[$controllerName]);
                            }
                            if ($mayAccess) {
                                $rolePermissionsByControllers[$controllerName][$accessMethod]['is_authorized'] = true;
                            }
                        }
                    }
                }
            }

            return $rolePermissionsByControllers;
        });

        return $rolePermissionsByControllers;
    }

    public function getControllerPermissionsByActions(array $data, string $controller, array $controllersAndActions): array 
    {
        $mode = $this->params->get('role_permissions_mode');

        if ($mode !== 'read-write') {
            // [action1 => 1, action2 => 0, etc]
            $controllerPermissionsByActions = $data;
        } else {
            /* Définit les permissions de chaque action du controller en fonction des paramètres 'read' et 'write'
            Sous forme : ['action1' => 1, 'action2' => 0, etc] */
            $controllerPermissionsByActions = [];
            if (isset($controllersAndActions[$controller]) && isset($controllersAndActions[$controller]['actions'])) {
                if (isset($controllersAndActions[$controller]['actions']['read']) && isset($data['read'])) {
                    foreach ($controllersAndActions[$controller]['actions']['read'] as $action) {
                        if (isset($action['action'])) {
                            $controllerPermissionsByActions[$action['action']] =  $data['read'] ? 1 : 0;
                        }
                    }
                }
                if (isset($controllersAndActions[$controller]['actions']['write']) && isset($data['write'])) {
                    foreach ($controllersAndActions[$controller]['actions']['write'] as $action) {
                        if (isset($action['action'])) {
                            $controllerPermissionsByActions[$action['action']] =  $data['write'] ? 1 : 0;
                        }
                    }
                }
            }
        }

        return $controllerPermissionsByActions;
    }

    public function rolePermissionsDataHasFormatError($data) {
        if (!is_array($data)) {
            return ['message' => 'Format JSON invalide.'];
        }

        foreach ($data as $controller => $actions) {
            if (!is_string($controller) || !class_exists($controller)) {
                ['message' => "Le contrôleur {$controller} est invalide."];
            }

            if (!is_array($actions)) {
                ['message' => "Les actions pour le contrôleur {$controller} doivent être un objet."];
            }

            foreach ($actions as $actionOrMode => $isAuthorized) {
                if (!is_string($actionOrMode) || !is_bool($isAuthorized)) {
                    return ['message' => "L'action ou le mode {$actionOrMode} pour le contrôleur {$controller} doit être une chaîne de caractères avec une valeur booléenne."];
                }
            }
        }

        return false;
    }
}
