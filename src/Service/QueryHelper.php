<?php

namespace App\Service;

use Doctrine\ORM\QueryBuilder;

class QueryHelper
{
    public function applyPagination(QueryBuilder $queryBuilder, int $page, int $limit): void
    {
        $offset = ($page - 1) * $limit;
        $queryBuilder->setFirstResult($offset)->setMaxResults($limit);
    }

    public function applyGlobalSearch(QueryBuilder $queryBuilder, ?string $search, array $fields): void
    {
        if ($search) {
            $orX = $queryBuilder->expr()->orX();
            foreach ($fields as $field) {
                $orX->add($queryBuilder->expr()->like($field, ':search'));
            }
            $queryBuilder->andWhere($orX)->setParameter('search', '%' . $search . '%');
        }
    }

    public function applySearchByField(QueryBuilder $queryBuilder, ?array $params, array $fields, string $alias): void
    {
        foreach ($fields as $field) {
            if ($params[$field]) {
                $queryBuilder->andWhere($alias . '.' . $field . ' LIKE :search_' . $field)
                    ->setParameter('search_' . $field, '%' . $params[$field]. '%');
            }
        }
    }

    public function applySorting(QueryBuilder $queryBuilder, ?string $orderBy, ?string $orderDir, string $alias): void
    {
        if ($orderBy) {
            $direction = strtoupper($orderDir) === 'DESC' ? 'DESC' : 'ASC';
            $queryBuilder->orderBy($alias . '.' . $orderBy, $direction);
        }
    }

    public function buildPagination(array $results, int $page, int $limit, int $totalResults): array
    {
        $offset = ($page - 1) * $limit;
        return [
            'items' => $results,
            'pagination' => [
                'totalItems' => $totalResults,
                'currentPage' => $page,
                'totalPages' => (int) ceil($totalResults / $limit),
                'startItem' => $totalResults > 0 ? $offset + 1 : 0,
                'endItem' => min($offset + $limit, $totalResults),
            ]
        ];
    }
}
