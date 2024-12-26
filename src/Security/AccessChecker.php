<?php

namespace App\Security;

use App\Service\UserPermissionsService;
use Symfony\Bundle\SecurityBundle\Security as SecurityBundleSecurity;


class AccessChecker
{
    private SecurityBundleSecurity $security;
    private UserPermissionsService $userPermissionsService;
    private $permissions;
    private $authorizedControllersAndActions;


    public function __construct(UserPermissionsService $userPermissionsService, SecurityBundleSecurity $security)
    {
        $this->security = $security;
        $this->userPermissionsService = $userPermissionsService;
        $this->permissions = [];
        $this->authorizedControllersAndActions = [
            'App\Controller\ProfileController' => ['getProfile', 'updateProfile', 'editProfilePassword'],
            'App\Controller\RegistrationController' => ['register', 'forgotPassword', 'verifyEmail', 'resetPassword', 'resendValidationEmail']
        ];
    }


    public function isAllowed(string $controller, string $action): bool
    {
        $user = $this->security->getUser();

        if (!$user) {
            return false;
        }

        // Si l'utilisateur a le rôle ROLE_SUPERADMIN, il a tous les droits
        if (in_array('ROLE_SUPERADMIN', $user->getRoles())) {
            return true;
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

