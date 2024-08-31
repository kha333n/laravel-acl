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

        // If the resource is scopeable, get the model from route model binding or throw an exception
        if ($this->policyRepository->isScopeable($resource, $action)) {
            $model = $request->route($resource);

            if (!$model) {
                throw new UnauthorizedException(__("laravel-acl::laravel_acl_languages.model_not_found"));
            }
        }

        if (!$this->policyRepository->isAuthorized($user, $resource, $action, $user)) {
            abort(403, __("laravel-acl::laravel_acl_languages.no_permission"));
        }

        return $next($request);
    }
}
