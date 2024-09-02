<?php

namespace Kha333n\LaravelAcl\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Kha333n\LaravelAcl\Models\Action;
use Kha333n\LaravelAcl\Models\Resource;
use ReflectionClass;

class UpdateResourcesAndActions extends Command
{
    protected $signature = 'acl:update-resources';
    protected $description = 'Scan models, extract resources and actions, and update the database.';


    protected array $resourcesToUpdate = [];

    public function handle(): void
    {
        $this->info('Scanning for resources and actions...');

        $modelsPath = app_path('Models');
        $modelFiles = $this->getAllModelFiles($modelsPath);
        $this->info('Found ' . count($modelFiles) . ' model files.');

        // Include additional classes and resources from config
        $additionalResources = config('laravel-acl.classes', []);
        $modelFiles = array_merge($modelFiles, $additionalResources);
        $this->info('Including ' . count($additionalResources) . ' additional classes.');

        foreach ($modelFiles as $modelClass) {
            if (class_exists($modelClass)) {
                $this->processModel($modelClass);
            }
        }

        $customResources = config('laravel-acl.custom_resources', []);
        $this->info('Including ' . count($customResources) . ' custom resources.');

        foreach ($customResources as $resource) {
            $this->processCustomResource($resource);
        }

        $this->info('Updating database with resources and actions...');
        $this->updateDatabase();

        $this->info('Database updated with resources and actions.');
    }

    protected function getAllModelFiles($directory): array
    {
        $files = [];
        foreach (File::allFiles($directory) as $file) {
            $namespace = $this->getNamespaceFromFile($file);
            $className = $namespace . '\\' . $file->getFilenameWithoutExtension();
            $files[] = $className;
        }

        return $files;
    }

    protected function getNamespaceFromFile($file): ?string
    {
        $contents = File::get($file);
        if (preg_match('/namespace\s+([^;]+);/', $contents, $matches)) {
            return $matches[1];
        }
        return null;
    }

    protected function processModel($modelClass): void
    {
        $reflection = new ReflectionClass($modelClass);

        if (!$reflection->hasMethod('getResourceName') || !$reflection->hasMethod('getActions')) {
            $this->warn("Skipping $modelClass: Required methods not found.");
            return;
        }

        $resourceData = $modelClass::getResourceName();
        $actionsData = $modelClass::getActions();

        $this->validateResourceData($resourceData, $modelClass);
        $this->validateActionsData($actionsData, $modelClass);

        $this->resourcesToUpdate[] = [
            'resource' => $resourceData,
            'actions' => $actionsData,
        ];
    }

    protected function validateResourceData($resourceData, $modelClass): void
    {
        if (!is_array($resourceData) || !isset($resourceData['name']) || !is_string($resourceData['name'])) {
            throw new \InvalidArgumentException("Invalid resource data in $modelClass.");
        }

        if (!isset($resourceData['description'])) {
            $resourceData['description'] = null;
        }
    }

    protected function validateActionsData($actionsData, $modelClass): void
    {
        if (!is_array($actionsData) || empty($actionsData)) {
            throw new \InvalidArgumentException("Invalid actions data in $modelClass.");
        }

        foreach ($actionsData as $actionData) {
            if (!is_array($actionData) || !isset($actionData['action']) || !is_string($actionData['action'])) {
                throw new \InvalidArgumentException("Invalid action format in $modelClass.");
            }

            if (!isset($actionData['is_scopeable']) || !is_bool($actionData['is_scopeable'])) {
                throw new \InvalidArgumentException("Invalid action in $modelClass resource: 'is_scopeable' is required and must be a boolean.");
            }

            if (!isset($actionData['description'])) {
                $actionData['description'] = null;
            }
        }
    }

    protected function processCustomResource(array $resource): void
    {
        // Validate the resource structure
        if (!isset($resource['name']) || !is_string($resource['name'])) {
            throw new \InvalidArgumentException('Invalid custom resource: "name" is required and must be a string.');
        }

        if (!isset($resource['description'])) {
            $resource['description'] = null;  // Set description to null if not provided
        }

        if (!isset($resource['actions']) || !is_array($resource['actions'])) {
            throw new \InvalidArgumentException('Invalid custom resource: "actions" must be an array.');
        }

        foreach ($resource['actions'] as $action) {
            if (!isset($action['action']) || !is_string($action['action'])) {
                throw new \InvalidArgumentException('Invalid action in custom resource: "action" is required and must be a string.');
            }

            if (!isset($action['is_scopeable']) || !is_bool($action['is_scopeable'])) {
                throw new \InvalidArgumentException('Invalid action in custom resource: "is_scopeable" is required and must be a boolean.');
            }

            if (!isset($action['description'])) {
                $action['description'] = null;  // Set description to null if not provided
            }
        }

        // Collect the valid resource and its actions
        $this->resourcesToUpdate[] = [
            'resource' => [
                'name' => $resource['name'],
                'description' => $resource['description'],
            ],
            'actions' => $resource['actions'],
        ];
    }

    protected function updateDatabase(): void
    {
        foreach ($this->resourcesToUpdate as $resourceData) {
            $resource = Resource::updateOrCreate(
                ['name' => $resourceData['resource']['name']],
                ['description' => $resourceData['resource']['description']]
            );

            foreach ($resourceData['actions'] as $actionData) {
                Action::updateOrCreate(
                    ['resource_id' => $resource->id, 'name' => $actionData['action']],
                    ['description' => $actionData['description'], 'is_scopeable' => $actionData['is_scopeable']],
                );
            }
        }
    }
}
