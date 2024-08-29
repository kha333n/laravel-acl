<?php

namespace Kha333n\LaravelAcl\Repositories;

use Carbon\Carbon;
use Carbon\Exceptions\InvalidFormatException;
use Exception;
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
            throw new InvalidPolicyException("Invalid or missing 'Version' field.");
        }

        // Validate the "definition" field
        if (!isset($policyJson['definitions']) || !is_array($policyJson['definitions']) || count($policyJson['definitions']) < 1) {
            throw new InvalidPolicyException("The 'definitions' array must contain at least one item.");
        }

        foreach ($policyJson['definitions'] as $def) {
            // Validate the "Effect" field
            if (!isset($def['Effect']) || !in_array($def['Effect'], ['Allow', 'Reject'])) {
                throw new InvalidPolicyException("Invalid or missing 'Effect' in definition. Must be 'Allow' or 'Reject'.");
            }

            // Validate the "Actions" field
            if (!isset($def['Actions']) || (!is_array($def['Actions']) && $def['Actions'] !== '*')) {
                throw new InvalidPolicyException("Invalid 'Actions' field. Must be '*' or an array of actions.");
            }

            // Validate the "Resource" field
            if (!isset($def['Resource']) || !$this->validateResource($def['Resource'])) {
                throw new InvalidPolicyException("Invalid 'Resource' field.");
            }

            // Validate the "Teams" field (optional)
            if (isset($def['TeamMode'])) {
                if (!in_array($def['TeamMode'], ['session', 'all'])) {
                    throw new InvalidPolicyException("Invalid or missing 'TeamMode'. Must be 'session' or 'all'.");
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
    private function validateResource($resource): bool
    {
        $prefix = config('laravel-acl.prefix');
        $parts = explode('::', $resource);
        if (count($parts) !== 3) {
            throw new InvalidPolicyException("Invalid resource format: {$resource}");
        }
        if ($parts[0] !== $prefix) {
            throw new InvalidPolicyException("Invalid resource prefix: {$parts[0]}. Expected: {$prefix}");
        }

        $tempResource = Resource::where('name', $parts[1])->first();

        if (!$tempResource) {
            throw new InvalidPolicyException("Resource not found: {$parts[1]}");
        }

        if (!isset($parts[2])) {
            throw new InvalidPolicyException("Invalid resource format: {$resource}");
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
                    throw new InvalidPolicyException("Invalid IP address or range: {$ip}");
                }
            }
        }

        if (isset($conditions['time'])) {
            if (!$this->validateTime($conditions['time'])) {
                throw new InvalidPolicyException("Invalid time format: {$conditions['time']}. Expected format: HH:MM. OR dd:mm:yyyy HH:MM, single or range seprated by -.");
            }
        }

        // Validate "daysOfWeek"
        if (isset($conditions['daysOfWeek'])) {
            if (!is_array($conditions['daysOfWeek'])) {
                throw new InvalidPolicyException("Invalid 'daysOfWeek' field. Must be an array.");
            }
            foreach ($conditions['daysOfWeek'] as $day) {
                if (!in_array($day, ['Monday', 'Tuesday', 'Wednesday', 'Thursday', 'Friday', 'Saturday', 'Sunday'])) {
                    throw new InvalidPolicyException("Invalid day of week: {$day}");
                }
            }
        }

        // Validate "User-Agent"
        if (isset($conditions['User-Agent'])) {
            if (!is_string($conditions['User-Agent'])) {
                throw new InvalidPolicyException("Invalid 'User-Agent' field. Must be a string.");
            }
        }

        // Validate "resourceAttributes"
        if (isset($conditions['resourceAttributes'])) {
            if (!is_array($conditions['resourceAttributes'])) {
                throw new InvalidPolicyException("Invalid 'resourceAttributes' field. Must be an array.");
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

    public function validateDateTime(string $datetime): bool
    {
        $datetimes = explode('-', $datetime);

        foreach ($datetimes as $singleDateTime) {
            if (!$this->isValidDateTimeFormat($singleDateTime)) {
                throw new InvalidFormatException("Invalid datetime format: $datetime");
            }
        }

        if (count($datetimes) === 2) {
            try {
                $start = Carbon::createFromFormat('d:m:Y H:i', trim($datetimes[0]));
                $end = Carbon::createFromFormat('d:m:Y H:i', trim($datetimes[1]));

                // Ensure the start and end datetime formats are valid
                if ($start->format('d:m:Y H:i') !== trim($datetimes[0]) || $end->format('d:m:Y H:i') !== trim($datetimes[1])) {
                    throw new Exception("Invalid datetime format: $datetime");
                }

                // Check that the end time is at least 1 minute after the start time
                if ($start->diffInMinutes($end, false) < 1) {
                    throw new Exception("End datetime must be at least 1 minute after the start: $datetime");
                }
            } catch (InvalidFormatException $e) {
                throw new InvalidPolicyException("Invalid datetime: " . $e->getMessage());
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

    public function validateSimpleTime(string $time): bool
    {
        $times = explode('-', $time);

        foreach ($times as $singleTime) {
            if (!$this->isValidTimeFormat($singleTime)) {
                throw new InvalidPolicyException("Invalid time format: $time");
            }
        }

        if (count($times) === 2) {
            $start = Carbon::createFromFormat('H:i', trim($times[0]));
            $end = Carbon::createFromFormat('H:i', trim($times[1]));

            // Ensure the start and end time formats are valid
            if ($start->format('H:i') !== trim($times[0]) || $end->format('H:i') !== trim($times[1])) {
                throw new InvalidPolicyException("Invalid time format: $time");
            }

            if ($start->diffInMinutes($end, false) === 0) {
                throw new InvalidPolicyException("Start and end times must be different: $time");
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

    protected function validateResourceAttributes(array $attributes): void
    {
        foreach ($attributes as $attribute => $condition) {
            if (!is_string($condition)) {
                throw new InvalidPolicyException("Invalid value for resource attribute '$attribute'. Must be a string.");
            }

            $parts = explode('::', $condition, 2);
            if (count($parts) != 2) {
                throw new InvalidPolicyException("Invalid format for resource attribute '$attribute'. Must be 'keyword::value'.");
            }

            $keyword = $parts[0];
            $value = $parts[1];

            if (!in_array($keyword, ['equal', 'include', 'any'])) {
                throw new InvalidPolicyException("Invalid keyword '$keyword' for resource attribute '$attribute'. Must be 'equal', 'include', or 'any'.");
            }

            if ($keyword === 'equal' || $keyword === 'include') {
                // For 'equal' and 'include', value should be a single element
                if (strpos($value, ',') !== false) {
                    throw new InvalidPolicyException("Invalid value for '$keyword' condition in resource attribute '$attribute'. Should be a single value, not comma-separated.");
                }
            } elseif ($keyword === 'any') {
                // For 'any', value can be comma-separated
                $values = explode(',', $value);
                if (count($values) < 1) {
                    throw new InvalidPolicyException("Invalid value for 'any' condition in resource attribute '$attribute'. Must contain at least one comma-separated value.");
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

    public function isAuthorized($user, $resource, $action, $resourceToCheck = null): bool
    {
        if (is_object($resourceToCheck)) {
            $key = $resourceToCheck->getKey();
        } else {
            $key = $resourceToCheck;
        }
        //TODO: Implement this method
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

        // Check if the user's IP address is allowed
        if (!$this->checkIpIsAllowed($mergedConditions['ips'])) {
            return false;
        }

        // Check if the current time is allowed
        if (!$this->checkTimeIsAllowed($mergedConditions['times'])) {
            return false;
        }

        dd('isAuthorized', $mergedConditions, now());

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

    private function getAllApplicablePolicies($user)
    {
        $directPolicies = $user->policies;

        $policiesViaRoles = $user->roles->map(function ($role) {
            return $role->policies;
        })->flatten();

        $policiesViaTeamDirect = $user->teams->map(function ($team) {
            return $team->policies;
        })->flatten();

        $policiesViaTeamRoles = $user->teams->map(function ($team) {
            return $team->roles->map(function ($role) {
                return $role->policies;
            })->flatten();
        })->flatten();

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
}
