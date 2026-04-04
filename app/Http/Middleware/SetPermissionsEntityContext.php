<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Spatie\Permission\PermissionRegistrar;
use Symfony\Component\HttpFoundation\Response;

class SetPermissionsEntityContext
{
    public function handle(Request $request, Closure $next): Response
    {
        $registrar = app(PermissionRegistrar::class);

        $entityId = $request->header('X-Entity-Id');

        if ($entityId === null && $request->hasSession()) {
            $entityId = $request->session()->get('current_entity_id');
        }

        if ($entityId === null && $request->user()) {
            $entityId = $request->user()->primaryEntity()?->getKey();
        }

        $registrar->setPermissionsTeamId($entityId);

        try {
            return $next($request);
        } finally {
            $registrar->setPermissionsTeamId(null);
        }
    }
}
