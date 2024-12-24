<?php

namespace App\Repository;

use App\Entity\Role;
use App\Service\QueryHelper;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Role>
 */
class RoleRepository extends ServiceEntityRepository
{
    private QueryHelper $queryHelper;

    public function __construct(ManagerRegistry $registry, QueryHelper $queryHelper)
    {
        parent::__construct($registry, Role::class);
        $this->queryHelper = $queryHelper;
    }

    public function getPaginatedRolesData(array $params): array
    {
        $page = $params['page'];
        $limit = $params['limit'];

        // Requête pour récupérer les résultats paginés
        $queryBuilder = $this->createQueryBuilder('r');
        $queryBuilder->andWhere('r.id NOT LIKE :superadmin_role')
            ->setParameter('superadmin_role', 'ROLE_SUPERADMIN');

        // Si un terme de recherche est fourni, ajout de conditions de filtre sur lastname, firstname ou email
        $this->queryHelper->applyGlobalSearch($queryBuilder, $params['search'], ['r.id', 'r.description']);

        $this->queryHelper->applySorting($queryBuilder, $params['orderBy'], $params['orderDir'], 'r');
        $this->queryHelper->applyPagination($queryBuilder, $page, $limit);

        $results = $queryBuilder->getQuery()->getResult();
        $totalResults = count($results);

        return $this->queryHelper->buildPagination($results, $page, $limit, $totalResults);
    }
}
