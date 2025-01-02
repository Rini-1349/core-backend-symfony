<?php

namespace App\Repository;

use App\Entity\Role;
use App\Service\QueryHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Symfony\Bundle\SecurityBundle\Security;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    private QueryHelper $queryHelper;
    private Security $security;

    public function __construct(ManagerRegistry $registry, QueryHelper $queryHelper, Security $security)
    {
        parent::__construct($registry, Role::class);
        $this->queryHelper = $queryHelper;
        $this->security = $security;
    }

    public function getPaginatedRolesData(array $params): array
    {
        $page = $params['page'];
        $limit = $params['limit'];
        $user = $this->security->getUser();

        // Requête pour récupérer les résultats paginés
        $queryBuilder = $this->createQueryBuilder('r');
        // Ne pas récupérer le rôle SUPERADMIN
        $queryBuilder->andWhere('r.id NOT LIKE :superadmin_role')
            ->setParameter('superadmin_role', 'ROLE_SUPERADMIN');
        // Si l'utilisateur courant n'est pas superadmin -> ne pas récupérer ses propres rôles
        if (!in_array("ROLE_SUPERADMIN", $user->getRoles())) {
            $queryBuilder->andWhere('r.id NOT IN (:user_roles)')
                ->setParameter('user_roles', $user->getRoles());
        }

        // Si un terme de recherche est fourni, ajout de conditions de filtre sur lastname, firstname ou email
        $this->queryHelper->applyGlobalSearch($queryBuilder, $params['search'], ['r.id', 'r.description']);

        $this->queryHelper->applySorting($queryBuilder, $params['orderBy'], $params['orderDir'], 'r');
        $this->queryHelper->applyPagination($queryBuilder, $page, $limit);

        $results = $queryBuilder->getQuery()->getResult();
        $totalResults = count($results);

        return $this->queryHelper->buildPagination($results, $page, $limit, $totalResults);
    }

    public function getIndexedRoles(array $roleIds) {
        
        // Récupérer les détails des rôles associés
        $roles = $this->createQueryBuilder('r')
            ->where('r.id IN (:ids)')
            ->setParameter('ids', $roleIds)
            ->getQuery()
            ->getResult();

        // Indexer les rôles par ID
        $indexedRoles = [];
        foreach ($roles as $role) {
            $indexedRoles[$role->getId()] = [
                'id' => $role->getId(),
                'description' => $role->getDescription()
            ];
        }

        return $indexedRoles;
    }

    public function findForSelect(): array
    {
        $roles = $this->createQueryBuilder('r')
            ->where('r.id != (:role_superadmin)')
            ->setParameter('role_superadmin', "ROLE_SUPERADMIN")
            ->orderBy('r.description', 'ASC')
            ->getQuery()
            ->getResult();

        $rolesForSelect = [];
        foreach ($roles as $role) {
            $rolesForSelect[$role->getId()] = $role->getDescription();
        }

        return $rolesForSelect;
    }
}
