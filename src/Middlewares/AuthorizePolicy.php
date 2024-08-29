<?php

namespace Kha333n\LaravelAcl\Middlewares;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Kha333n\LaravelAcl\Exceptions\UnauthorizedException;
use Kha333n\LaravelAcl\Repositories\LaravelAclRepository;
use Kha333n\LaravelAcl\Repositories\PolicyRepository;

class AuthorizePolicy
{
    protected $policyRepository;

    public function __construct(LaravelAclRepository $policyRepository)
    {
        $this->policyRepository = $policyRepository;
    }

    /**
     * Handle an incoming request.
     *
     * @param \Illuminate\Http\Request $request
     * @param \Closure $next
     * @param string $action
     * @param string $resource
     * @return mixed
     */
    public function handle(Request $request, Closure $next, string $action, string $resource)
    {
        $user = Auth::user();

        // If resource is scopeable, get the model from route model binding or throw an exception
        if ($this->policyRepository->isScopeable($resource, $action)) {
            $model = $request->route($resource);

            if (!$model) {
                throw new UnauthorizedException("For scopeable actions, the specified resource must be provided in the
                route using route model bindng. Or use authorizePolicy() helper function for manually passing resource
                id to authorize.");
            }
            $key = $model->getKey();
        } else {
            $key = null;
        }

        if (!$this->policyRepository->isAuthorized($user, $resource, $action, $key)) {
            abort(403, "You do not have permission to perform this action on the specified resource.");
        }

        return $next($request);
    }
}
