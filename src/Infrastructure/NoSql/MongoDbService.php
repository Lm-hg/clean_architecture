<?php

declare(strict_types=1);

namespace Infrastructure\NoSql;

use RuntimeException;

/**
 * JSON File Service for NoSQL operations
 * Handles pricing grids, logs, flexible data storage using JSON files
 * Clean Architecture - Infrastructure Layer
 */
class JsonFileService
{
    private string $dataPath;

    public function __construct(string $dataPath = null)
    {
        $this->dataPath = $dataPath ?? __DIR__ . '/../../../data/json/';
        
        if (!is_dir($this->dataPath)) {
            throw new RuntimeException("Data directory does not exist: {$this->dataPath}");
        }
    }

    /**
     * Insert a document into a JSON file collection
     */
    public function insertOne(string $collection, array $document): string
    {
        try {
            $filePath = $this->getFilePath($collection);
            $data = $this->readJsonFile($filePath);
            
            // Generate unique ID if not provided
            if (!isset($document['id'])) {
                $document['id'] = $this->generateId();
            }
            
            $data[] = $document;
            $this->writeJsonFile($filePath, $data);
            
            return (string) $document['id'];
        } catch (\Exception $e) {
            throw new RuntimeException("JSON insert error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Insert multiple documents into a JSON file collection
     */
    public function insertMany(string $collection, array $documents): array
    {
        try {
            $filePath = $this->getFilePath($collection);
            $data = $this->readJsonFile($filePath);
            $insertIds = [];
            
            foreach ($documents as $document) {
                if (!isset($document['id'])) {
                    $document['id'] = $this->generateId();
                }
                $insertIds[] = (string) $document['id'];
                $data[] = $document;
            }
            
            $this->writeJsonFile($filePath, $data);
            return $insertIds;
        } catch (\Exception $e) {
            throw new RuntimeException("JSON insertMany error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find documents in a JSON file collection
     */
    public function find(string $collection, array $filter = [], array $options = []): array
    {
        try {
            $filePath = $this->getFilePath($collection);
            $data = $this->readJsonFile($filePath);
            
            // Apply filters
            if (!empty($filter)) {
                $data = array_filter($data, function($document) use ($filter) {
                    return $this->matchesFilter($document, $filter);
                });
            }
            
            // Apply limit option
            if (isset($options['limit'])) {
                $data = array_slice($data, 0, $options['limit']);
            }
            
            return array_values($data);
        } catch (\Exception $e) {
            throw new RuntimeException("JSON find error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Find a single document in a JSON file collection
     */
    public function findOne(string $collection, array $filter = [], array $options = []): ?array
    {
        $results = $this->find($collection, $filter, ['limit' => 1]);
        return !empty($results) ? $results[0] : null;
    }

    /**
     * Update multiple documents in a JSON file collection
     */
    public function updateMany(string $collection, array $filter, array $update): int
    {
        try {
            $filePath = $this->getFilePath($collection);
            $data = $this->readJsonFile($filePath);
            $updatedCount = 0;
            
            for ($i = 0; $i < count($data); $i++) {
                if ($this->matchesFilter($data[$i], $filter)) {
                    foreach ($update as $field => $value) {
                        $data[$i][$field] = $value;
                    }
                    $updatedCount++;
                }
            }
            
            if ($updatedCount > 0) {
                $this->writeJsonFile($filePath, $data);
            }
            
            return $updatedCount;
        } catch (\Exception $e) {
            throw new RuntimeException("JSON updateMany error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Update a single document in a JSON file collection
     */
    public function updateOne(string $collection, array $filter, array $update): bool
    {
        try {
            $filePath = $this->getFilePath($collection);
            $data = $this->readJsonFile($filePath);
            
            for ($i = 0; $i < count($data); $i++) {
                if ($this->matchesFilter($data[$i], $filter)) {
                    foreach ($update as $field => $value) {
                        $data[$i][$field] = $value;
                    }
                    $this->writeJsonFile($filePath, $data);
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            throw new RuntimeException("JSON updateOne error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete multiple documents from a JSON file collection
     */
    public function deleteMany(string $collection, array $filter): int
    {
        try {
            $filePath = $this->getFilePath($collection);
            $data = $this->readJsonFile($filePath);
            $originalCount = count($data);
            
            $data = array_filter($data, function($document) use ($filter) {
                return !$this->matchesFilter($document, $filter);
            });
            
            $deletedCount = $originalCount - count($data);
            
            if ($deletedCount > 0) {
                $this->writeJsonFile($filePath, array_values($data));
            }
            
            return $deletedCount;
        } catch (\Exception $e) {
            throw new RuntimeException("JSON deleteMany error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Delete a single document from a JSON file collection
     */
    public function deleteOne(string $collection, array $filter): bool
    {
        try {
            $filePath = $this->getFilePath($collection);
            $data = $this->readJsonFile($filePath);
            
            for ($i = 0; $i < count($data); $i++) {
                if ($this->matchesFilter($data[$i], $filter)) {
                    array_splice($data, $i, 1);
                    $this->writeJsonFile($filePath, $data);
                    return true;
                }
            }
            
            return false;
        } catch (\Exception $e) {
            throw new RuntimeException("JSON deleteOne error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Count documents in a JSON file collection
     */
    public function countDocuments(string $collection, array $filter = []): int
    {
        try {
            $results = $this->find($collection, $filter);
            return count($results);
        } catch (\Exception $e) {
            throw new RuntimeException("JSON count error: " . $e->getMessage(), 0, $e);
        }
    }

    /**
     * Get file path for a collection
     */
    private function getFilePath(string $collection): string
    {
        return $this->dataPath . $collection . '.json';
    }

    /**
     * Read JSON file
     */
    private function readJsonFile(string $filePath): array
    {
        if (!file_exists($filePath)) {
            return [];
        }
        
        $content = file_get_contents($filePath);
        if ($content === false) {
            throw new RuntimeException("Unable to read file: {$filePath}");
        }
        
        $data = json_decode($content, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException("Invalid JSON in file: {$filePath}");
        }
        
        return $data ?? [];
    }

    /**
     * Write JSON file
     */
    private function writeJsonFile(string $filePath, array $data): void
    {
        $json = json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        if ($json === false) {
            throw new RuntimeException("Unable to encode JSON data");
        }
        
        if (file_put_contents($filePath, $json, LOCK_EX) === false) {
            throw new RuntimeException("Unable to write file: {$filePath}");
        }
    }

    /**
     * Check if document matches filter
     */
    private function matchesFilter(array $document, array $filter): bool
    {
        foreach ($filter as $field => $value) {
            if (!isset($document[$field]) || $document[$field] !== $value) {
                return false;
            }
        }
        return true;
    }

    /**
     * Generate unique ID
     */
    private function generateId(): string
    {
        return uniqid('', true);
    }
}