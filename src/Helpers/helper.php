<?php

use Illuminate\Database\Eloquent\Model;
use Kha333n\LaravelAcl\Exceptions\InvalidPolicyException;
use Kha333n\LaravelAcl\Repositories\LaravelAclRepository;

if (!function_exists('laravelAclValidatePolicy')) {
    /**
     * Validate a policy JSON using the PolicyRepository.
     *
     * @param string|array $policyJson
     * @return void
     * @throws InvalidPolicyException
     */
    function laravelAclValidatePolicy(string|array $policyJson): void
    {
        if (is_string($policyJson)) {
            $policyJson = json_decode($policyJson, true);
        }

        $policyRepository = app(LaravelAclRepository::class);

        $policyRepository->validatePolicyJson($policyJson);
    }
}

if (!function_exists('authorizePolicy')) {
    /**
     * Authorize a policy action on a resource.
     *
     * @param string $resource
     * @param string $action
     * @param Model|null $resourceToCheck
     * @param mixed|null $authenticatedModel
     * @return void
     */
    function authorizePolicy(string $resource, string $action, Model $resourceToCheck = null, mixed $authenticatedModel = null): bool
    {
        if (!$authenticatedModel) {
            if (Auth::guest()) {
                return false;
            }
            $authenticatedModel = Auth::user();
        }

        $policyRepository = app(LaravelAclRepository::class);

        if (!$policyRepository->isAuthorized($authenticatedModel, $resource, $action, $resourceToCheck)) {
            return false;
        }

        return true;
    }
}
