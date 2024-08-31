<?php

namespace Kha333n\LaravelAcl\Repositories;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
use Illuminate\Database\Eloquent\Model;
use IPLib\Factory;
use Kha333n\LaravelAcl\Exceptions\InvalidPolicyException;
use Kha333n\LaravelAcl\Models\Resource;

class LaravelAclRepository
{
    /**
     * Validate the structure of a policy JSON.
     *
     * @param array $policyJson
     * @return array
     * @throws InvalidPolicyException
     */
    public function validatePolicyJson(array $policyJson): array
    {
        // Validate the "Version" field
        if (!isset($policyJson['Version'])) {
            throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.version_missing'));
        }

        // Validate the "definition" field
        if (!isset($policyJson['definitions']) || !is_array($policyJson['definitions']) || count($policyJson['definitions']) < 1) {
            throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.definition_array_min_items'));
        }

        foreach ($policyJson['definitions'] as $def) {
            // Validate the "Effect" field
            if (!isset($def['Effect']) || !in_array($def['Effect'], ['Allow', 'Reject'])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_effect_key'));
            }

            // Validate the "Actions" field
            if (!isset($def['Actions']) || (!is_array($def['Actions']) && $def['Actions'] !== '*')) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_actions_key'));
            }

            // Validate the "Resource" field
            if (!isset($def['Resource']) || !$this->validateResource($def['Resource'], $def['Actions'])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_resource_key'));
            }

            // Validate the "Teams" field (optional)
            if (isset($def['TeamMode'])) {
                if (!in_array($def['TeamMode'], ['session', 'all'])) {
                    throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_or_missing_team_mode'));
                }
            }

            // Validate the "Conditions" field (optional)
            if (isset($def['Conditions'])) {
                $this->validateConditions($def['Conditions']);
            }
        }

        return $policyJson;
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateResource($resource, $actions): bool
    {
        $prefix = config('laravel-acl.prefix');
        $parts = explode('::', $resource);
        if (count($parts) !== 3) {
            throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_resource_format', ['resource' => $resource]));
        }
        if ($parts[0] !== $prefix) {
            throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_resource_parts', ['part' => $parts[0], 'prefix' => $prefix]));
        }

        $tempResource = Resource::where('name', $parts[1])->first();

        if (!$tempResource) {
            throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.resource_not_found', ['resource' => $parts[1]]));
        }

        if ($actions !== '*' && !$tempResource->actions->whereIn('name', $actions)->count()) {
            throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_actions_for_resource', ['resource' => $parts[1]]));
        }

        if (!isset($parts[2])) {
            throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_resource_scopes', ['resource' => $parts[1]]));
        }
        return true;
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateConditions(array $conditions): void
    {
        if (isset($conditions['ips'])) {
            foreach ($conditions['ips'] as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP) && !$this->validateIpRange($ip)) {
                    throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_ips', ['ip' => $ip]));
                }
            }
        }

        if (isset($conditions['time'])) {
            if (!$this->validateTime($conditions['time'])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_time', ['time' => $conditions['time']]));
            }
        }

        // Validate "daysOfWeek"
        if (isset($conditions['daysOfWeek'])) {
            if (!is_array($conditions['daysOfWeek'])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_days_of_week'));
            }
            foreach ($conditions['daysOfWeek'] as $day) {
                if (!in_array($day, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])) {
                    throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_day', ['day' => $day]));
                }
            }
        }

        // Validate "User-Agent"
        if (isset($conditions['User-Agent'])) {
            if (!is_string($conditions['User-Agent'])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_user_agent'));
            }
        }

        // Validate "resourceAttributes"
        if (isset($conditions['resourceAttributes'])) {
            if (!is_array($conditions['resourceAttributes'])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_resource_attributes'));
            }
            $this->validateResourceAttributes($conditions['resourceAttributes']);
        }
    }

    private function validateIpRange(string $ip): bool
    {
        // Check if it's a valid CIDR notation
        if ($this->validateCidr($ip)) {
            return true;
        }

        // Check if it's a valid IP range
        if ($this->validateIpRangeFormat($ip)) {
            return true;
        }

        // If it doesn't match any known format, it's invalid
        return false;
    }

    private function validateCidr(string $cidr): bool
    {
        // Split CIDR notation into IP and prefix length
        $parts = explode('/', $cidr);
        if (count($parts) === 2 && filter_var($parts[0], FILTER_VALIDATE_IP)) {
            $prefixLength = (int)$parts[1];
            // Check prefix length is within valid range (0-32 for IPv4, 0-128 for IPv6)
            return ($prefixLength >= 0 && $prefixLength <= 128);
        }
        return false;
    }

    private function validateIpRangeFormat(string $range): bool
    {
        // Split the range into start and end IPs
        $parts = explode('-', $range);
        if (count($parts) === 2) {
            $startIp = trim($parts[0]);
            $endIp = trim($parts[1]);

            // Both start and end IPs must be valid
            if (filter_var($startIp, FILTER_VALIDATE_IP) && filter_var($endIp, FILTER_VALIDATE_IP)) {
                // Compare the IPs to ensure the range is valid (start IP must be <= end IP)
                return $this->compareIps($startIp, $endIp) <= 0;
            }
        }
        return false;
    }

    private function compareIps(string $ip1, string $ip2): int
    {
        // Convert IPs to packed binary strings for comparison
        $packedIp1 = inet_pton($ip1);
        $packedIp2 = inet_pton($ip2);

        // Compare the binary strings
        return strcmp($packedIp1, $packedIp2);
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateTime(string $time): bool
    {
        // Check for single date-time or range of date-time values
        if (preg_match('/^\d{2}:\d{2}:\d{4} \d{2}:\d{2}(-\d{2}:\d{2}:\d{4} \d{2}:\d{2})?$/', $time)) {
            return $this->validateDateTime($time);
        }

        // Check for simple time format or a time range
        if (preg_match('/^\d{2}:\d{2}(-\d{2}:\d{2})?$/', $time)) {
            return $this->validateSimpleTime($time);
        }

        return false;
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateDateTime(string $datetime): bool
    {
        $datetimes = explode('-', $datetime);

        foreach ($datetimes as $singleDateTime) {
            if (!$this->isValidDateTimeFormat($singleDateTime)) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_time', ['time' => $datetime]));
            }
        }

        if (count($datetimes) === 2) {
            try {
                $start = Carbon::createFromFormat('d:m:Y H:i', trim($datetimes[0]));
                $end = Carbon::createFromFormat('d:m:Y H:i', trim($datetimes[1]));

                // Ensure the start and end datetime formats are valid
                if ($start->format('d:m:Y H:i') !== trim($datetimes[0]) || $end->format('d:m:Y H:i') !== trim($datetimes[1])) {
                    throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_time', ['time' => $datetime]));
                }

                // Check that the end time is at least 1 minute after the start time
                if ($start->diffInMinutes($end, false) < 1) {
                    throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_datetime_end', ['datetime' => $datetime]));
                }
            } catch (InvalidFormatException $e) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_time', ['time' => $datetime]));
            }
        }
        return true;
    }

    private function isValidDateTimeFormat(string $datetime): bool
    {
        $parsed = Carbon::createFromFormat('d:m:Y H:i', $datetime);
        // Ensure that the parsed datetime matches the input and is a valid datetime
        return $parsed && $parsed->format('d:m:Y H:i') === $datetime;
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateSimpleTime(string $time): bool
    {
        $times = explode('-', $time);

        foreach ($times as $singleTime) {
            if (!$this->isValidTimeFormat($singleTime)) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_time', ['time' => $time]));
            }
        }

        if (count($times) === 2) {
            $start = Carbon::createFromFormat('H:i', trim($times[0]));
            $end = Carbon::createFromFormat('H:i', trim($times[1]));

            // Ensure the start and end time formats are valid
            if ($start->format('H:i') !== trim($times[0]) || $end->format('H:i') !== trim($times[1])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_time', ['time' => $time]));
            }

            if ($start->diffInMinutes($end, false) === 0) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.start_end_time_same', ['time' => $time]));
            }
        }

        return true;
    }

    private function isValidTimeFormat(string $time): bool
    {
        $parsed = Carbon::createFromFormat('H:i', $time);

        // Ensure that the parsed time matches the input and is a valid time
        return $parsed && $parsed->format('H:i') === $time && $parsed->hour < 24 && $parsed->minute < 60;
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateResourceAttributes(array $attributes): void
    {
        foreach ($attributes as $attribute => $condition) {
            if (!is_string($condition)) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_value_for_resource_attribute', ['attribute' => $attribute]));
            }

            $parts = explode('::', $condition, 2);
            if (count($parts) != 2) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_format_for_resource_attribute', ['attribute' => $attribute]));
            }

            $keyword = $parts[0];
            $value = $parts[1];

            if (!in_array($keyword, ['equal', 'include', 'any'])) {
                throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_operator_for_resource_attribute', ['attribute' => $attribute]));
            }

            if ($keyword === 'equal' || $keyword === 'include') {
                // For 'equal' and 'include', value should be a single element
                if (strpos($value, ',') !== false) {
                    throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_operator_value_for_resource_attribute', ['keyword' => $keyword, 'attribute' => $attribute]));
                }
            } elseif ($keyword === 'any') {
                // For 'any', value can be comma-separated
                $values = explode(',', $value);
                if (count($values) < 1) {
                    throw new InvalidPolicyException(__('laravel-acl::laravel_acl_languages.invalid_operator_value_for_resource_attribute_any', ['keyword' => $keyword, 'attribute' => $attribute]));
                }
            }
        }
    }

    public function isScopeable(string $resource, string $action)
    {
        try {
            $resource = Resource::where('name', $resource)->first();

            if (!$resource) {
                return false;
            }

            $action = $resource->actions->where('name', $action)->first();

            if (!$action) {
                return false;
            }

            return $action->is_scopeable;
        } catch (Exception $e) {
            return false;
        }
    }

    public function isAuthorized($user, $resource, $action, Model $resourceToCheck = null): bool
    {
        if (is_object($resourceToCheck)) {
            $key = $resourceToCheck->getKey();
        } else {
            $key = $resourceToCheck;
        }

        $resource = Resource::where('name', $resource)->first();

        if (!$resource) {
            return false;
        }

        if ($action !== '*') {
            $action = $resource->actions->where('name', $action)->first();

            if (!$action) {
                return false;
            }
        }

        $policies = $this->getAllApplicablePolicies($user);

        $statements = $this->getRelevantPoliciesStatements(
            $resource, is_string($action) ? $action : $action->name,
            $policies
        );

        if ($statements->isEmpty()) {
            return false;
        }

        // any statement with effect deny will deny access
        if ($statements->contains('Effect', 'Reject')) {
            return false;
        }

        if (!$this->scopeToResource($action, $statements, $resource, $key)) {
            return false;
        }
        $mergedConditions = $this->getMergedConditions($statements);

        // Check if the user's IP address is allowed
        if (!$this->checkIpIsAllowed($mergedConditions['ips'])) {
            return false;
        }

        // Check if the current time is allowed
        if (!$this->checkTimeIsAllowed($mergedConditions['times'])) {
            return false;
        }

        // Check if the current day of the week is allowed
        if (count($mergedConditions['daysOfWeek']) > 0) {
            $currentDayOfWeek = Carbon::now()->format('l');
            if (!in_array($currentDayOfWeek, $mergedConditions['daysOfWeek'])) {
                return false;
            }
        }

        // Check if the User-Agent is allowed
        if (count($mergedConditions['User-Agent']) > 0) {
            $userAgent = request()->header('User-Agent');

            $isAllowed = false;
            // Loop through each User-Agent condition to check for a match
            foreach ($mergedConditions['User-Agent'] as $allowedUserAgent) {
                if (strpos($userAgent, $allowedUserAgent) !== false) {
                    $isAllowed = true;
                    break; // Break the loop if a match is found
                }
            }

            if (!$isAllowed) {
                return false;
            }
        }

        if ($action->is_scopeable && $resourceToCheck instanceof Model && isset($mergedConditions['resourceAttributes'])) {
            return $this->checkAttributesMatched($mergedConditions['resourceAttributes'], $resourceToCheck);
        }

        return true;
    }

    private function getAllApplicablePolicies($user)
    {
        $directPolicies = $user->policies;

        $policiesViaRoles = $user->roles->map(function ($role) {
            return $role->policies;
        })->flatten();

        if (config('laravel-acl.teams.enabled')) {
            $teamId = session()->has('team_id') ? session('team_id') : null;

            $policiesViaTeamDirect = $user->teams->map(function ($team) use ($teamId) {
                return $team->policies->filter(function ($policy) use ($team, $teamId) {
                    // Include the policy if it's in 'all' mode or if it's in 'session' mode and the team ID matches
                    return !isset($policy['TeamMode']) ||
                        $policy['TeamMode'] === 'all' ||
                        ($policy['TeamMode'] === 'session' && $team->id == $teamId);
                });
            })->flatten();

            $policiesViaTeamRoles = $user->teams->map(function ($team) use ($teamId) {
                return $team->roles->map(function ($role) use ($team, $teamId) {
                    return $role->policies->filter(function ($policy) use ($team, $teamId) {
                        // Apply the same filtering logic as above
                        return !isset($policy['TeamMode']) ||
                            $policy['TeamMode'] === 'all' ||
                            ($policy['TeamMode'] === 'session' && $team->id == $teamId);
                    });
                })->flatten();
            })->flatten();
        } else {
            $policiesViaTeamDirect = collect();
            $policiesViaTeamRoles = collect();
        }

        return collect($directPolicies)
            ->merge($policiesViaRoles)
            ->merge($policiesViaTeamDirect)
            ->merge($policiesViaTeamRoles)
            // pluck just the policy_json field
            ->pluck('policy_json')
            ->map(function ($policyJson) {
                return json_decode($policyJson, true);
            });
    }

    private function getRelevantPoliciesStatements($resource, $action, $policies)
    {
        $statements = $policies->map(function ($policy) {
            return $policy['definitions'];
        })->pluck('Statement');

        return $statements->filter(function ($statement) use ($resource, $action) {
            if ($action === '*') {
                // regex match for resource in this anything::resource::action format
                return preg_match("/^.+::{$resource->name}::.+$/", $statement['Resource']);
            } else {
                if (!is_array($statement['Actions'])) {
                    return preg_match("/^.+::{$resource->name}::.+$/", $statement['Resource']);
                }
                return (
                    preg_match("/^.+::{$resource->name}::.+$/", $statement['Resource'])
                    &&
                    in_array($action, $statement['Actions'])
                );
            }
        });
    }

    /**
     * @param $action
     * @param $statements
     * @param Resource $resource
     * @param $key
     * @return bool
     */
    private function scopeToResource($action, $statements, Resource $resource, $key): bool
    {
        if ($action->is_scopeable) {
            // Iterate over the statements to check the 'Resource' field
            $statements->contains(function ($statement) use ($resource, $key) {
                // Extract the resource and key list from the 'Resource' field
                $resourceKeyList = explode('::', $statement['Resource']);
                $keys = isset($resourceKeyList[2]) ? $resourceKeyList[2] : '';

                // Convert the comma-separated list of keys into an array
                $keysArray = explode(',', $keys);

                // Check if the specific key exists in the array
                if (!in_array($key, $keysArray)) {
                    return false;
                }
            });
        }
        return true;
    }

    /**
     * @param mixed $statements
     * @return \Illuminate\Support\Collection
     */
    private function getMergedConditions(mixed $statements): \Illuminate\Support\Collection
    {
        // if conditions exists in any statement, check if they are satisfied
        $mergedConditions = [
            'ips' => [],
            'times' => [],
            'daysOfWeek' => [],
            'User-Agent' => [],
            'resourceAttributes' => []
        ];

        $statements->pluck('Conditions')->each(function ($conditions) use (&$mergedConditions) {
            if (isset($conditions['ips'])) {
                $mergedConditions['ips'] = array_merge($mergedConditions['ips'], $conditions['ips']);
            }
            if (isset($conditions['time'])) {
                $mergedConditions['times'][] = $conditions['time'];
            }
            if (isset($conditions['daysOfWeek'])) {
                $mergedConditions['daysOfWeek'] = array_merge($mergedConditions['daysOfWeek'], $conditions['daysOfWeek']);
            }
            if (isset($conditions['User-Agent'])) {
                $mergedConditions['User-Agent'][] = $conditions['User-Agent'];
            }
            if (isset($conditions['resourceAttributes'])) {
                foreach ($conditions['resourceAttributes'] as $attribute => $condition) {
                    $mergedConditions['resourceAttributes'][$attribute][] = $condition;
                }
            }
        });

        $mergedConditions = collect($mergedConditions);
        return $mergedConditions;
    }

    private function checkIpIsAllowed(array $ips): bool
    {
        $userIp = request()->ip();

        if (!$userIp) {
            return false; // Return false if no IP is found
        }

        // Parse the user's IP address using the IP library
        $userIpAddress = Factory::parseAddressString($userIp);

        if (!$userIpAddress) {
            return false; // Return false if the IP could not be parsed
        }

        foreach ($ips as $ip) {
            if ($this->validateIpRange($ip)) {
                // Parse the range using the IP library
                $ipRange = Factory::parseRangeString($ip);

                if ($ipRange && $ipRange->contains($userIpAddress)) {
                    return true;
                }
            } else {
                // Single IP case
                if ($userIp === $ip) {
                    return true;
                }
            }
        }
        return false;
    }

    private function checkTimeIsAllowed($times): bool
    {
        $currentDateTime = Carbon::now(); // Get the current date and time

        foreach ($times as $time) {
            // If the time format is date-time or a date-time range
            if ($this->validateTime($time)) {
                if (preg_match('/^\d{2}:\d{2}:\d{4} \d{2}:\d{2}(-\d{2}:\d{2}:\d{4} \d{2}:\d{2})?$/', $time)) {
                    // Date-time or date-time range case
                    $datetimes = explode('-', $time);

                    // Single date-time case
                    if (count($datetimes) === 1) {
                        $endDateTime = Carbon::createFromFormat('d:m:Y H:i', trim($datetimes[0]));
                        if ($currentDateTime->lessThanOrEqualTo($endDateTime)) {
                            return true;
                        }
                    } // Date-time range case
                    elseif (count($datetimes) === 2) {
                        $startDateTime = Carbon::createFromFormat('d:m:Y H:i', trim($datetimes[0]));
                        $endDateTime = Carbon::createFromFormat('d:m:Y H:i', trim($datetimes[1]));

                        if ($currentDateTime->between($startDateTime, $endDateTime, true)) {
                            return true;
                        }
                    }
                } // If the time format is simple time or a time range
                elseif (preg_match('/^\d{2}:\d{2}(-\d{2}:\d{2})?$/', $time)) {
                    $times = explode('-', $time);

                    // Single time case
                    if (count($times) === 1) {
                        $endTime = Carbon::createFromTimeString(trim($times[0]));
                        $endDateTime = $currentDateTime->copy()->setTimeFrom($endTime);
                        if ($currentDateTime->lessThanOrEqualTo($endDateTime)) {
                            return true;
                        }
                    } // Time range case
                    elseif (count($times) === 2) {
                        $startTime = Carbon::createFromTimeString(trim($times[0]));
                        $endTime = Carbon::createFromTimeString(trim($times[1]));

                        $startDateTime = $currentDateTime->copy()->setTimeFrom($startTime);
                        $endDateTime = $currentDateTime->copy()->setTimeFrom($endTime);

                        // Handle cases where the end time is on the next day
                        if ($endDateTime->lessThan($startDateTime)) {
                            $endDateTime->addDay();
                        }

                        if ($currentDateTime->between($startDateTime, $endDateTime, true)) {
                            return true;
                        }
                    }
                }
            }
        }

        return false;
    }

    /**
     * @param $resourceAttributes
     * @param Model $resourceToCheck
     * @return bool
     */
    private function checkAttributesMatched($resourceAttributes, Model $resourceToCheck): bool
    {
        $attributes = $resourceAttributes;

        $passCheckList = collect();
        foreach ($attributes as $attribute => $conditions) {
            // Check if the attribute exists on the model
            if (!isset($resourceToCheck->$attribute) && !method_exists($resourceToCheck, $attribute)) {
                continue; // Skip if the attribute does not exist on the model
            }

            $resourceValue = $resourceToCheck->$attribute;
            $attributePass = false;

            foreach ($conditions as $condition) {
                [$operator, $value] = explode('::', $condition, 2);

                switch ($operator) {
                    case 'equal':
                        if ($resourceValue == $value) {
                            $attributePass = true;
                        }
                        break;

                    case 'include':
                        if (is_string($resourceValue) && (strpos($resourceValue, $value) !== false)) {
                            $attributePass = true;
                        }
                        break;

                    case 'any':
                        $valueList = explode(',', $value);
                        if (in_array($resourceValue, $valueList)) {
                            $attributePass = true;
                        }
                        break;

                    default:
                        // Handle unexpected operators if needed
                        break;
                }

                // If one condition passes for this attribute, no need to check further conditions
                if ($attributePass) {
                    break;
                }
            }
            if ($attributePass) {
                $passCheckList->push(true);
            } else {
                $passCheckList->push(false);
            }
        }

        // If no conditions passed for this attribute, return false
        if ($passCheckList->contains(false)) {
            return false;
        }

        return true;
    }
}
