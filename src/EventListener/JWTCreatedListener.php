<?php 

namespace App\EventListener;

use App\Service\UserPermissionsService;
use Lexik\Bundle\JWTAuthenticationBundle\Event\JWTCreatedEvent;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class JWTCreatedListener
{
    private TagAwareCacheInterface $cache;
    private UserPermissionsService $userPermissionsService;

    public function __construct(TagAwareCacheInterface $cache, UserPermissionsService $userPermissionsService)
    {
        $this->userPermissionsService = $userPermissionsService;
        $this->cache = $cache;
    }

    public function onJWTCreated(JWTCreatedEvent $event)
    {
        // Récupérer l'utilisateur actuellement connecté
        $user = $event->getUser();

        // Vérifiez que l'utilisateur implémente bien UserInterface
        if (!$user instanceof UserInterface) {
            return;
        }

        $this->cache->delete("userPermissions-" . $user->getId());

        // Get user permissions
        $permissions = $this->userPermissionsService->loadPermissions($user);
        $aliasesPermissionsList = $this->userPermissionsService->formatPermissionsIntoAliases($permissions);

        // Récupérez le payload actuel et ajoutez les champs personnalisés
        $payload = $event->getData();
        $payload['id'] = $user->getId();
        $payload['lastname'] = $user->getLastname();
        $payload['firstname'] = $user->getFirstname();
        $payload['is_verified'] = $user->getIsVerified();
        $payload['exp'] = strtotime("tomorrow 0:00"); // Le lendemain à minuit
        $payload['permissions'] = $aliasesPermissionsList;

        // Redéfinir le payload dans l'événement
        $event->setData($payload);
    }
}
