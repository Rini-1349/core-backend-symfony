<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class UserService
{
    private ParameterBagInterface $params;
    private TagAwareCacheInterface $cache;

    public function __construct(ParameterBagInterface $params, TagAwareCacheInterface $cache)
    {
        $this->params = $params;
        $this->cache = $cache;
    }

    public function getRoleIds($users): array 
    {
        // Extraire tous les rôles (ids) des utilisateurs
        $roleIds = [];
        foreach ($users as $user) {
            foreach ($user->getRoles() as $roleId) {
                if (!in_array($roleId, $roleIds)) {
                    $roleIds[] = $roleId;
                }
            }
        }

        return $roleIds;
    }

    public function getRolesDescriptions($user, $indexedRoles): array 
    {
        $rolesDescriptions = [];
        foreach ($user->getRoles() as $roleId) {
            if (isset($indexedRoles[$roleId])) {
                $rolesDescriptions[] = $indexedRoles[$roleId]['description'];
            }
        }

        return $rolesDescriptions;
    }
}

