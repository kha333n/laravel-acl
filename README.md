# Laravel ACL (Access Control List) Package

#### A Laravel package for managing roles, policies, teams, and permissions.

#### Author: [kha333n](https://github.com/kha333n)

## key features:

- **Policy:** A detailed definition of what to allow or deny to which user under what conditions. In simple words, a
  permission but with complete control over the conditions.
- **Role:** A collection of policies.
- **Team:** A collection of users (OR Linked Model).
- **Direct Policy Assignment:** Assigning a policy directly to a user (Model).
- **Role Policy Assignment:** Assigning a role to a user (Model) and then assigning policies to the role.
- **Team Policy Assignment:** Assigning a team to a user (Model) and then assigning policies to the team.

## Table of Contents

- [Requirements](#requirements)
- [Installation](#installation)
- [How This Works](#how-this-works)
- [Configuration](#configuration)
- [Usage](#usage)
- [WHY THIS](#why-this)
- [Examples](#examples)
- [Testing](#testing)
- [Contributing](#contributing)
- [License](#license)
- [Credits](#credits)
- [Changelog](#changelog)

## Requirements

- PHP 8.0 or higher
- illuminate/support 8.0 or higher
- mlocati/ip-lib 1.18.0 or higher

## Installation

```bash
composer require kha333n/laravel-acl
```
Optionally, Publish the configuration file
```bash
php artisan vendor:publish --tag=laravel-acl-config
```
And if required, publish the migration files
```bash
php artisan vendor:publish --tag=laravel-acl-migrations
```
publish the translation files
```bash
php artisan vendor:publish --tag=laravel-acl-translations
```

Run the migrations
```bash
php artisan migrate
```

## How This Works
This system has two main parts:
- **Resource:** A resource is a string that represents a model or a class in the system.
- **Action:** An action is a string that represents an action that can be performed on a resource.

Example:
In a system has Book Model and all possible actions on the Book Model are:
- Resource: `book`
- Action: `create, read, update, delete, print`

Then these two strings will be combined
to create a policies which will define to whom and under what conditions these actions are allowed or denied.

**Like this:**

- Allow a user to create books.
- Deny a user to delete books.
- Allow a user to read books only if the book is published.
- Deny a user to delete books.
- Allow a user to print books only if at times from 09:00 to 17:00.

These conditions will be defined in policies. Explained in the upcoming section.

## Configuration
This package is configured using the `config/acl.php` file and `.env` file.

### Available configuration options are:
### Prefix:
 Prefix is the global prefix for all resource strings in the system.

## **NOTE:** 
    It should be defined once and should not be changed after the system is in use.
    Policies use this string to define the resource string.
    If you change this string, all policies will be invalid.
    It's recommended to define it once and only change it only when absolutely necessary.
    And then re-define all policies.

Default: `acl` 

Where it is used: resources strings in policies. E.g. `acl.user.create` 

Can be changed in the `.env` file as `LARAVEL_ACL_PREFIX`
```dotenv
LARAVEL_ACL_PREFIX=star-wars
```
OR in the `config/acl.php` file as
```php
'prefix' => 'star-wars',
```

### Teams:
 Should teams be used in the system or not?
 
Default: `false`

Can be changed in the `.env` file as `LARAVEL_ACL_TEAMS_ENABLED`
```dotenv
LARAVEL_ACL_TEAMS_ENABLED=true
```
OR in the `config/acl.php` file as
```php
'teams' => [
        'enabled' => true,
    ],
```

### Classes:
 All models in the default model directory will be scanned automatically.
But any model outside the default directory should be defined here.
Other than models, all other classes that required to be scanned for resources and actions
listing should be defined here.

There will be some actions that will not be depended on models.
Like `Print Report` on `Reports`.
In that case, its resource and actions string will be defined in Reports Class,
and then this class will be defined here. 
Defined in `config/acl.php` file.
```php
    'classes' => [
        // 'App\Models\CustomModel',
        'App\Controllers\ReportsController',
    ],
```

### Custom Resources and Actions:
 If there are some resources and actions that are not depended on models or any class, they can be defined here.
 Defined in `config/acl.php` file.
```php
    'custom_resources' => [
        'resource1' => [
            'name' => 'resource1',
            'description' => 'Resource 1',
            'actions' => [
                [
                    'action' => 'action1',
                    'description' => 'Action 1',
                    'is_scopeable' => true
                ],
                [
                    'action' => 'action2',
                    'description' => 'Action 2',
                    'is_scopeable' => false
                ]
            ]
        ]
    ],
```

## Usage

### Policies Definition:
Policies are JSON strings that define the conditions under which an action is allowed or denied.

Structure of a policy:
```json
{
    "name": "policy1", 
    "policy_json": {
        "Version": "2012-10-17",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Reject",
                    "Actions": [
                        "view"
                    ],
                    "Resource": "acl::user::*",
                    "TeamMode": "session",
                    "Conditions": {
                        "ips": [
                            "10.11.12.13",
                            "0.0.0.0/128",
                            "10.10.10.50-10.10.10.50"
                        ],
                        "time": "20:08:2024 12:00-20:08:2024 12:01",
                        "daysOfWeek": [
                            "Monday",
                            "Tuesday"
                        ],
                        "User-Agent": "Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/58.0.3029.110 Safari/537.3",
                        "resourceAttributes": {
                            "owner": "equal::kha333n",
                            "type": "include::user",
                            "size": "any::100,200"
                        }
                    }
                }
            }
        ]
    },
    "description": "policy1 description"
}
```
Explanation:
- `name`: Name of the policy, for reference, has no effect on the policy.
- `policy_json`: JSON string that defines the policy.
- `Version`: Version of the policy. You can set anything here. Or define the proper version for history.
- `definitions`: Array of definitions. A policy can contain multiple definitions in single policy. These all definitions will apply when the policy is applied.
- `Statement`: A single definition.
- `Effect`: Allow or Reject. Allow must be defined to allow an action. By default, all actions are rejected. Deny can be used to explicitly deny some action. E.g.: In a role an action is allowed, but you want to deny that action to user 5 explicitly.
- `Actions`: Array of actions. All actions that are allowed or denied by this definition. Use `*` to allow or deny all actions within the resource. Other resources actions will not be affected.
- `Resource`: Resource string. It is a unique string in the system which identifies some resource in the system. It is constructed as follows:

    `prefix` + `::` + `resource` + `::` + `scope`
    
    - `prefix`: Defined in the configuration file. It is a global prefix for all resources in the system.
    - `resource`: Resource string. It is a unique string in the system which identifies some resource in the system. It is defined in the Model or Class. It Will be explained.
    - `scope`: Scope of the resource. Define `*` to allow all resources or define a specific resource. E.g. `acl::user::*`, `acl::user::5`, specific user with id 5.

- `TeamMode`: Mode of the team. It can be `session` or `all`. If the team is defined in the session, then the team will be the team of the user in the current session. If the team is defined as all, then it will apply for all teams. If a user has teas `TeamA` and `TeamB`, 
   if session is defined, it will apply to the team which is in the session as key `team_id`. If all is defined, it will apply to both teams.
- `Conditions`: Conditions under which the policy will apply.
    - `ips`: Array of IPs. If the user's IP is in this array, the policy will apply. IP can be in the following formats:
        - `Single IP`: Define a single IP. E.g. `1.1.1.1`.
        - `CIDR`: Define a CIDR range. E.g. `1.1.1.1/32`.
        - `Range`: Define a range. E.g. `1.1.1.1-1.1.1.9`.
    - `time`: Time range. If the current time is in this range, the policy will apply. Time can be in the following formats:
        - `Single Time`: Define a single time. E.g. `12:00`. It means from day start till this time. from `00:00` to `12:00`. Daily basis.
        - `Time Range`: Define a time range. E.g. `12:00-13:00`. It means from `12:00` to `13:00`. Daily basis. 
          In case if you defined time range in reverse order like `13:00-12:00`, it means that it will be from `13:00` to `12:00` of the next day. OR denied from `12:00` to `13:00`. 
        - `Date Time Range`: Define a date time range. E.g. `20:08:2024 12:00-20:08:2024 12:01`. It will be between this specific date time range.
    - `daysOfWeek`: Array of days. If the current day is in this array, the policy will apply. Following values supported:
        - `Monday`, `Tuesday`, `Wednesday`, `Thursday`, `Friday`, `Saturday`, `Sunday`.
    - `User-Agent`: User-Agent string. If the user's request User-Agent is equal OR partially matched to this string, the policy will apply. E.g., To only allow requests from Windows OS, you can define `(Windows NT 10.0; Win64; x64)`.
    - `resourceAttributes`: Array of resource attributes. These are the attributes of the resource. E.g., If the resource is a user, then the attributes can be `name`, `email`, `role`, etc. It has the following matching criteria:
        - NOTE: Resource attributes will only allow on scopeable actions. and only on model resources, not custom resources.
        - `equal::`: Equal to the value. E.g. `equal::kha333n`.
        - `include::`: Include the value. E.g. `include::user`. It checks partial match.
        - `any::`: Any of the values. E.g. `any::100,200`. It checks if the value is in the list.

#### Evaluation precedence:
- It will first scope to resource.
- If it is being applied to a scopeable action, it will consider the scope of resource else will skip this step.
- Then it will check the Effect. If it is `Reject`, it will reject the action.
- In case of `Allow`, it will check further conditions.
- TeamMode: It might get a little confusing here. That's why focus on it.
    - If teams are not enabled in config, it will simply ignore this condition.
    - If teams are enabled, it will get all policies that are in all teams of user (Model).
    - Then for each policy it will check what is team mode.
    - If it is a session, it will include that policy ONLY IF that team is now in the session.
    - If it is all, it will include that policy in any case.
  
  In simple words, if a policy is session mode, and it is attached to user through a team,
  and also that team is in the session, then that policy will be applied.
- Then it will check all conditions.
  If all conditions are met, it will allow the action.
  If any condition is not met, it will reject the action.
  It is an AND operation.
- Then for each condition inside conditions array it will perform OR operation.
  
- E.g.: If any one ip or ip range is matched, it will allow the action. same for the others.
- Except the resourceAttributes, it will perform AND operation on all attributes.
  And each attribute has its own condition to check the value.
  `equal::`, `include::`, `any::`.
- E.g.: If the owner is equal to `kha333n`, and a type is included in `user`, and size is any of `100,200`, then it will allow the action.
- If any of the defined attributes in policy do not exist in the resource, it will be ignored.


[//]: # (advanced-usage:)

### Resource and Actions Definition:
Resources and actions are defined in the Model or Class.

Implement `AclInterface` in each Model or Class.
```php
use Kha333n\Acl\AclInterface;

class User extends Model implements AclInterface
{
    
    #[ArrayShape(['name' => "string", 'description' => "string"])] 
    public static function getResourceName(): array
    {
        return ['name' => 'users', 'description' => 'User resource'];
    }

    #[ArrayShape(['action' => "string", 'description' => "string"])] 
    public static function getActions(): array
    {
        return [
            ['action' => 'create', 'description' => 'Create user', 'is_scopeable' => false],
            ['action' => 'read', 'description' => 'Read user', 'is_scopeable' => true],
            ['action' => 'update', 'description' => 'Update user', 'is_scopeable' => true],
            ['action' => 'delete', 'description' => 'Delete user', 'is_scopeable' => true],
        ];
    }
}
```

Then run the following command to scan all models and classes for resources and actions.
```bash
php artisan acl:update-resources
```
It will update a database for update resources and actions. Then those cna be used in policies.

### Policy Assignment:
Policies can be assigned to users directly, or through roles, or through teams.

1. Assigning directly to user.
```php
$user = User::find(1);
$policy = Policy::find(1);
$user->assignPolicy($policy);

// OR can remove
$user->revokePolicy($policy);
```
2. Assigning through a role.
```php
$user = User::find(1);
$role = Role::find(1);
$policy = Policy::find(1);
$role->assignPolicy($policy);
$user->assignRole($role);

// OR can remove policy from a role
$role->revokePolicy($policy);
// remove role from user
$user->revokeRole($role);
```
3. Assigning through a team.
```php
$user = User::find(1);
$team = Team::find(1);
$policy = Policy::find(1);
$team->assignPolicy($policy);
// OR role can be assigned to team indirectly attaching policy

$user->assignTeam($team);

// OR can remove policy from a team
$team->revokePolicy($policy);
// remove team from user
$user->revokeTeam($team);
```

### Authorization:
To check if a user is authorized to perform an action on a resource, there are two methods available.
1. **Middleware:** use middleware `authorize-policy` with parameter action and resource to apply middleware.

E.g.: To authorize those users who have access to read users on specific route apply like this.
```php
Route::get('/user/{id}', function ($id) {
    return User::find($id);
})->middleware('authorize-policy:read,users');
```

2. **Helper Function:** Use helper function `authorizePolicy()` to check if a user is authorized to perform an action on a resource.

This method gives more control over the authorization process. In this along with resource and action, 
you can pass Model on which checks will be performed, And Authenticated Model too if its not available via auth() helper function.

E.g.: To check if a user is authorized to read a user.
```php
// books controller class

public function show($id)
{
    authorizePolicy('read', 'books');
    // continue next...
}
```
Model on which performing action is not available via route model binding.
```php
public function show($id)
{
    $book = Book::find($id);
    authorizePolicy('read', 'books', $book);
    // continue next...
}
```

If a user is not authenticated or checking authorization on some other auth model.
```php
public function show($id)
{
    $book = Book::find($id);
    $author = Author::find(1);  // Author is also an authenticated model but not available via auth() helper function.
    authorizePolicy('read', 'books', $book, $author);
    // continue next...
}
```

## WHY THIS
##### A Good question, Very Good question!

There are loot of packages available for Roles and Permission,
and most of the time they are enough for the requirements.
Like Spatie Permissions.

**But imagine,**
You have a system for library management in which you have simple requirements:
Books, Shelf, Librarians, and Users Borrowing books.

In which you define permissions for each action then group them in librarian and user roles.

After a few days, you have a new requirement (not all at once, but they keep on coming or changing):
- A user can borrow a book only if the book is available.
- A user can borrow a book only if the book is available and the user has not borrowed more than 5 books.
- Librarian can only issue a book only on week days.
- Librarian can only issue a book only on week days and between 09:00 to 17:00.
- Admin wants a system to ban users.
- Librarians cannot access the system outside the library network.

These are those conditions that can't be handled by simple permissions.
You will need to programmatically add conditions for all these things.

#### BUT WAIT! This package is here to help you.

Using policies, you can handle all the above conditions and like this situation more gracefully and without
changing the code.

Of course, it will not always handle 100% conditions.
But it will handle more than 95% of those.
And reduce code and effort for other 5% too.

Sometimes you will need to add columns at db and some conditions in the code which policies then use.
But It's still better than all the conditions in the code.

If your system does not require complex conditions, you can still use policy as a simple permission.
And treat them as a simple role-permission-based system.
It is highly flexible for simplest to complex systems.

## Examples

1. **Simple Permission:**
```json
{
    "name": "edit-books", 
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "create"
                    ],
                    "Resource": "acl::books::*",
                    "Conditions": {}
                }
            }
        ]
    },
    "description": "Allow to create books"
}
```

2. **Complex Permission: Borrow only when available**
```json
{
    "name": "borrow-books", 
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "borrow"
                    ],
                    "Resource": "acl::books::*",
                    "Conditions": {
                        "resourceAttributes": {
                            "status": "equal::available"
                        }
                    }
                }
            }
        ]
    },
    "description": "Allow to borrow books"
}
```

3. **Complex Permission: Borrow only when available and user has not borrowed more than 5 books**
```json
{
    "name": "borrow-books",
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "borrow"
                    ],
                    "Resource": "acl::books::*",
                    "Conditions": {
                        "resourceAttributes": {
                            "status": "equal::available"
                        }
                    }
                }
            },
            {
                "Statement": {
                    "Effect": "Deny",
                    "Actions": [
                        "borrow"
                    ],
                    "Resource": "acl::user::*",
                    "Conditions": {
                        "resourceAttributes": {
                            "borrowed_books": "equal::5"
                        }
                    }
                }
            }
        ]
    },
    "description": "Allow to borrow books"
}
```

4. **Complex Permission: Issue book only on week days**
```json
{
    "name": "issue-books",
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "issue"
                    ],
                    "Resource": "acl::books::*",
                    "Conditions": {
                        "daysOfWeek": [
                            "Monday",
                            "Tuesday",
                            "Wednesday",
                            "Thursday",
                            "Friday"
                        ]
                    }
                }
            }
        ]
    },
    "description": "Allow to issue books"
}
```

5. **Complex Permission: Issue book only on week days and between 09:00 to 17:00**
```json
{
    "name": "issue-books",
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "issue"
                    ],
                    "Resource": "acl::books::*",
                    "Conditions": {
                        "daysOfWeek": [
                            "Monday",
                            "Tuesday",
                            "Wednesday",
                            "Thursday",
                            "Friday"
                        ],
                        "time": "09:00-17:00"
                    }
                }
            }
        ]
    },
    "description": "Allow to issue books"
}
```

6. **Complex Permission: Allow librarian to issue books to students only**
```json
{
    "name": "issue-books",
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "issue"
                    ],
                    "Resource": "acl::books::*",
                    "Conditions": {
                        "resourceAttributes": {
                            "type": "include::student"
                        }
                    }
                }
            }
        ]
    },
    "description": "Allow to issue books"
}
```

7. **Complex Permission: Allow librarian to manage library only on library network**
```json
{
    "name": "manage-library",
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "manage"
                    ],
                    "Resource": "acl::library::*",
                    "Conditions": {
                        "ips": [
                            "10.11.12.13",
                           ]
                    }
                }
            }
        ]
    },
    "description": "Allow to manage library"
}
```

8. **Complex Permission: Allow a user to edit only users with ids 1, 4, 5, 198**
```json
{
    "name": "edit-users",
    "policy_json": {
        "Version": "V1",
        "definitions": [
            {
                "Statement": {
                    "Effect": "Allow",
                    "Actions": [
                        "edit"
                    ],
                    "Resource": "acl::users::1,4,5,198",
                    "Conditions": {}
                }
            }
        ]
    },
    "description": "Allow to edit users"
}
```

[//]: # (testing:)

## Testing

will add

[//]: # (contributing:)

## Contributing

will add

[//]: # (license:)

## License

will add

[//]: # (credits:)

## Credits

will add

[//]: # (changelog:)

## Changelog

will add

[//]: # (end of README.md)

[//]: # (add name description initial introduction to the project)

