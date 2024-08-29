<?php

use Kha333n\LaravelAcl\Exceptions\InvalidPolicyException;

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

        $policyRepository = app(\Kha333n\LaravelAcl\Repositories\LaravelAclRepository::class);

        $policyRepository->validatePolicyJson($policyJson);
    }
}

if (!function_exists('authorizePolicy')) {
    /**
     * Authorize a policy action on a resource.
     *
     * @param string $resource
     * @param string $action
     * @param mixed $resourceId <h4>ID to check or Object of resource model. Without object will not check for resourceAttributes.</h4>
     * @param mixed $authenticatedModel
     * @return void
     */
    function authorizePolicy(string $resource, string $action, mixed $resourceId = null, $authenticatedModel = null): void
    {
        if (!$authenticatedModel) {
            if (Auth::guest()) {
                abort(403, "You do not have permission to perform this action on the specified resource.");
            }
            $authenticatedModel = Auth::user();
        }

        $policyRepository = app(\Kha333n\LaravelAcl\Repositories\LaravelAclRepository::class);

        if (!$policyRepository->isAuthorized($authenticatedModel, $resource, $action, $resourceId)) {
            abort(403, "You do not have permission to perform this action on the specified resource.");
        }
    }
}
