<?php 

namespace App\Service;

use Symfony\Component\HttpFoundation\Request;

class QueryParameterService
{
    public function extractParameters(Request $request, array $defaults = []): array
    {
        $parameters = [];
        foreach ($defaults as $key => $defaultValue) {
            $parameters[$key] = $request->get($key, $defaultValue);
        }
        return $parameters;
    }
}
