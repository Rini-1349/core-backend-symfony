<?php

namespace App\Repository;

use App\Entity\User;
use App\Service\QueryHelper;
use App\Service\UserService;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    private QueryHelper $queryHelper;
    private UserService $userService;
    private RoleRepository $roleRepository;

    public function __construct(ManagerRegistry $registry, QueryHelper $queryHelper, UserService $userService, RoleRepository $roleRepository)
    {
        parent::__construct($registry, User::class);
        $this->queryHelper = $queryHelper;
        $this->userService = $userService;
        $this->roleRepository = $roleRepository;
    }

    public function getPaginatedUsersData(array $params): array
    {
        $page = $params['page'];
        $limit = $params['limit'];

        // Requête pour récupérer les résultats paginés
        $queryBuilder = $this->createQueryBuilder('u');
        $queryBuilder->andWhere('u.roles NOT LIKE :superadmin_role')
            ->setParameter('superadmin_role', '%"ROLE_SUPERADMIN"%');
            
        // Si un terme de recherche est fourni, ajout de conditions de filtre sur lastname, firstname ou email
        $this->queryHelper->applyGlobalSearch($queryBuilder, $params['search'], ['u.lastname', 'u.firstname', 'u.email']);
        $this->queryHelper->applySearchByField($queryBuilder, $params, ['lastname', 'firstname', 'email'], 'u');

        if ($params['is_verified'] === "0" || $params['is_verified'] === "1") {
            $queryBuilder->andWhere('u.isVerified' . ' = :isVerified')
                    ->setParameter('isVerified', $params['is_verified']);
        }

        if (!is_null($params['roles_descriptions']) && str_contains($params['roles_descriptions'], "ROLE_")) {
            $queryBuilder->andWhere('u.roles LIKE :role_id')
                ->setParameter('role_id', '%"' . $params['roles_descriptions'] . '"%');
        }

        $this->queryHelper->applySorting($queryBuilder, $params['orderBy'], $params['orderDir'], 'u');
        $this->queryHelper->applyPagination($queryBuilder, $page, $limit);

        $results = $queryBuilder->getQuery()->getResult();

        $indexedRoles = $this->roleRepository->getIndexedRoles($this->userService->getRoleIds($results));
        foreach ($results as $user) {
            $rolesDescriptions = $this->userService->getRolesDescriptions($user, $indexedRoles);
            $user->setRolesDescriptions($rolesDescriptions);
        }

        $totalResults = count($results);

        return $this->queryHelper->buildPagination($results, $page, $limit, $totalResults);
    }
}