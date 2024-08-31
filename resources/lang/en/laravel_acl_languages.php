<?php

return [
    'version_missing' => 'Invalid or missing \'Version\' field.',
    'definition_array_min_items' => 'The \'definitions\' array must contain at least one item.',
    'invalid_effect_key' => "Invalid or missing 'Effect' in definition. Must be 'Allow' or 'Reject'.",
    'invalid_actions_key' => "Invalid 'Actions' field. Must be '*' or an array of actions.",
    'invalid_resource_key' => "Invalid 'Resource' field.",

    'invalid_resource_format' => "Invalid resource format: :resource",
    'invalid_resource_parts' => "Invalid resource prefix: :part. Expected: :prefix",
    'resource_not_found' => "Resource not found: :resource",
    'invalid_actions_for_resource' => "Invalid actions for resource: :resource",
    'invalid_resource_scopes' => "Invalid resource scopes for resource: :resource",

    'invalid_or_missing_team_mode' => "Invalid or missing 'TeamMode'. Must be 'session' or 'all'.",

    'invalid_ips' => "Invalid IP address or range: :ip",
    'invalid_time' => "Invalid time format: :time. Expected format: HH:MM. OR dd:mm:yyyy HH:MM, single or range separated by -.",
    'invalid_datetime_end' => "End datetime must be at least 1 minute after the start: :datetime",
    'start_end_time_same' => "Start and end time cannot be the same: :time",
    'invalid_days_of_week' => "Invalid 'daysOfWeek' field. Must be an array.",
    'invalid_day' => "Invalid day: :day",
    'invalid_user_agent' => "Invalid 'User-Agent' field. Must be a string.",

    'invalid_resource_attributes' => "Invalid 'resourceAttributes' field. Must be an array.",
    'invalid_value_for_resource_attribute' => "Invalid value for resource attribute: :attribute. Must be a string.",
    'invalid_format_for_resource_attribute' => "Invalid format for resource attribute: :attribute. Must be 'keyword::value'",
    'invalid_operator_for_resource_attribute' => "Invalid operator for resource attribute: :attribute. Must be 'equal', 'include', 'any'.",
    'invalid_operator_value_for_resource_attribute' => "Invalid value for ':keyword' condition in resource attribute ':attribute'. Should be a single value, not comma-separated.",
    'invalid_operator_value_for_resource_attribute_any' => "Invalid value for ':keyword' condition in resource attribute ':attribute'. Should be a comma-separated list of values.",

    'no_permission' => "You do not have permission to perform this action on the specified resource.",
    'model_not_found' => "For scopeable actions, the specified resource must be provided in the route using route model binding. Or use authorizePolicy() helper function for manually passing resource id to authorize.",
];
