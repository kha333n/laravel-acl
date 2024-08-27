<?php

namespace Kha333n\LaravelAcl\Repositories;

use Kha333n\LaravelAcl\Exceptions\InvalidPolicyException;
use Kha333n\LaravelAcl\Models\Resource;
use Kha333n\LaravelAcl\Models\Team;

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
        if (!isset($policyJson['definition']) || !is_array($policyJson['definition']) || count($policyJson['definition']) < 1) {
            throw new InvalidPolicyException("The 'definition' array must contain at least one item.");
        }

        foreach ($policyJson['definition'] as $def) {
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
            if (isset($def['Teams'])) {
                if (!is_array($def['Teams'])) {
                    throw new InvalidPolicyException("Invalid 'Teams' field. Must be an array.");
                }
                $this->validateTeams($def['Teams']);
            }

            // Validate the "Condition" field (optional)
            if (isset($def['Condition'])) {
                $this->validateConditions($def['Condition']);
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
        return true;
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateTeams(array $teams): void
    {
        if (!isset($teams['mode']) || !in_array($teams['mode'], ['session', 'all'])) {
            throw new InvalidPolicyException("Invalid or missing 'mode' in Teams. Must be 'session' or 'all'.");
        }

        if (!isset($teams['appliedTo']) || !is_array($teams['appliedTo'])) {
            throw new InvalidPolicyException("Invalid 'appliedTo' in Teams. Must be an array of team identifiers.");
        }

        foreach ($teams['appliedTo'] as $team) {
            if (!Team::where('name', $team)->exists()) {
                throw new InvalidPolicyException("Team not found: {$team}");
            }
        }
    }

    /**
     * @throws InvalidPolicyException
     */
    private function validateConditions(array $conditions): void
    {
        if (isset($conditions['ip'])) {
            foreach ($conditions['ip'] as $ip) {
                if (!filter_var($ip, FILTER_VALIDATE_IP) && !$this->validateIpRange($ip)) {
                    throw new InvalidPolicyException("Invalid IP address or range: {$ip}");
                }
            }
        }

        if (isset($conditions['time'])) {
            foreach ($conditions['time'] as $time) {
                if (!$this->validateTime($time)) {
                    throw new InvalidPolicyException("Invalid time format: {$time}. Expected format: HH:MM.");
                }
            }
        }

        // Add more conditions validations as needed
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
        try {
            // Check for date and time format (e.g., "05:04:2024 06:40")
            if (preg_match('/^\d{2}:\d{2}:\d{4} \d{2}:\d{2}$/', $time)) {
                return $this->validateDateTime($time);
            }

            // Check for simple time format (e.g., "06:40")
            if (preg_match('/^\d{2}:\d{2}$/', $time)) {
                return $this->validateSimpleTime($time);
            }

            return false; // Invalid format
        } catch (InvalidFormatException $e) {
            return false;
        }
    }

    private function validateDateTime(string $dateTime): bool
    {
        try {
            Carbon::createFromFormat('d:m:Y H:i', $dateTime);
            return true;
        } catch (InvalidFormatException $e) {
            return false;
        }
    }

    private function validateSimpleTime(string $time): bool
    {
        try {
            Carbon::createFromFormat('H:i', $time);
            return true;
        } catch (InvalidFormatException $e) {
            return false;
        }
    }

    private function validateTimeRange(string $timeRange): bool
    {
        // Split the time range by the '-' character
        $parts = explode('-', $timeRange);
        if (count($parts) !== 2) {
            return false; // Must be exactly two parts
        }

        // Validate each part
        $start = trim($parts[0]);
        $end = trim($parts[1]);

        return $this->validateTime($start) && $this->validateTime($end) &&
            $this->compareTime($start, $end) >= 0;
    }

    private function compareTime(string $time1, string $time2): int
    {
        try {
            $dateTime1 = $this->convertToCarbon($time1);
            $dateTime2 = $this->convertToCarbon($time2);

            return $dateTime1->gt($dateTime2) ? 1 : ($dateTime1->lt($dateTime2) ? -1 : 0);
        } catch (InvalidFormatException $e) {
            return -1; // Indicate invalid time comparison
        }
    }

    private function convertToCarbon(string $time): Carbon
    {
        if (preg_match('/^\d{2}:\d{2}:\d{4} \d{2}:\d{2}$/', $time)) {
            return Carbon::createFromFormat('d:m:Y H:i', $time);
        }

        return Carbon::createFromFormat('H:i', $time);
    }
}
