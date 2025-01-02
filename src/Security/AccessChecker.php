<?php

namespace App\Security;

use App\Service\UserPermissionsService;
use Symfony\Bundle\SecurityBundle\Security;
use App\Entity\User;


class AccessChecker
{
    private Security $security;
    private UserPermissionsService $userPermissionsService;
    private $permissions;
    private $authorizedControllersAndActions;


    public function __construct(UserPermissionsService $userPermissionsService, Security $security)
    {
        $this->security = $security;
        $this->userPermissionsService = $userPermissionsService;
        $this->permissions = [];
        $this->authorizedControllersAndActions = [
            'App\Controller\ProfileController' => ['getProfile', 'updateProfile', 'editProfilePassword'],
            'App\Controller\RegistrationController' => ['register', 'forgotPassword', 'verifyEmail', 'resetPassword', 'resendValidationEmail']
        ];
    }


    public function isAllowed(string $controller, string $action, array $attributes = []): bool
    {
        /** @var User $user */
        $user = $this->security->getUser();

        if (!$user) {
            return false;
        }

        // Si l'utilisateur a le rôle ROLE_SUPERADMIN, il a tous les droits
        if (in_array('ROLE_SUPERADMIN', $user->getRoles())) {
            return true;
        }

        // Pas d'autorisation de l'utilisateur sur lui-même
        if ($controller === 'App\Controller\UserController' && in_array($action, ['getUserDetails', 'updateUser', 'editUserPassword', 'deleteUser'])) {
            if (isset($attributes['id']) && $attributes['id'] == $user->getId()) {
                return false;
            }
        }

        // Pas d'autorisation de l'utilisateur sur son propre rôle (informations et permissions) ni sur le ROLE_SUPERADMIN
        if (($controller === 'App\Controller\RoleController' && in_array($action, ['getRoleDetails', 'updateRole'])) || 
        ($controller === 'App\Controller\RolePermissionController' && in_array($action, ['getRolePermissions', 'updateRolePermissions']))) {
            if (isset($attributes['id']) && (in_array($attributes['id'], $user->getRoles()) || $attributes['id'] === "ROLE_SUPERADMIN")) {
                return false;
            }
        }

        $this->permissions = $this->userPermissionsService->loadPermissions($user);

        if (isset($this->permissions[$controller]) && in_array($action, $this->permissions[$controller])) {
            return true;
        }

        return false;
    }

    public function isActionAuthorized($controller, $action) {
        if (isset($this->authorizedControllersAndActions[$controller]) && 
        in_array($action, $this->authorizedControllersAndActions[$controller])) {
            return true;
        }
        
        return false;
    }
}

