<?php

namespace App\Repository;

use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, User::class);
    }

    public function getPaginatedUsersData(array $params): array
    {
        $page = $params['page'];
        $limit = $params['limit'];
        $offset = ($page - 1) * $limit;

        // Requête pour récupérer les résultats paginés
        $queryBuilder = $this->createQueryBuilder('u')
            ->setFirstResult($offset)
            ->setMaxResults($limit);

        // Si un terme de recherche est fourni, ajout de conditions de filtre sur lastname, firstname ou email
        if ($params['search'] !== null) {
            $queryBuilder->andWhere('u.lastname LIKE :search OR u.firstname LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $params['search'] . '%');
        }
        foreach (['lastname', 'firstname', 'email'] as $field) {
            if ($params[$field]) {
                $queryBuilder->andWhere('u.' . $field . ' LIKE :search_' . $field)
                    ->setParameter('search_' . $field, '%' . $params[$field]. '%');
            }
        }

        if ($params['is_verified'] === "0" || $params['is_verified'] === "1") {
            $queryBuilder->andWhere('u.isVerified' . ' = :isVerified')
                    ->setParameter('isVerified', $params['is_verified']);
        }

        if ($params['orderBy']) {
            // Ajouter la clause ORDER BY en sécurisant les entrées
            $queryBuilder->orderBy('u.' . $params['orderBy'], strtoupper($params['orderDir']) === 'DESC' ? 'DESC' : 'ASC');
        }

        $results = $queryBuilder->getQuery()->getResult();
        $totalResults = count($results);

        return [
            'items' => $results,
            'pagination' => [
                'totalItems' => $totalResults,
                'currentPage' => $page,
                'totalPages' => ceil($totalResults / $limit),
                'startItem' => $offset + 1,
                'endItem' => min($offset + $limit, $totalResults),
            ]
        ];
    }
}