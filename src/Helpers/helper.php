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
