<?php

declare(strict_types=1);

namespace GroceryCrud\Upload;

use CodeIgniter\Files\File;
use CodeIgniter\HTTP\Files\UploadedFile;
use GroceryCrud\Config\Config;
use GroceryCrud\Exceptions\GroceryCrudException;

class UploadManager
{
    private Config $config;
    private string $uploadPath;
    private string $thumbnailPath;

    /** @var array<string, array<string, mixed>> */
    private array $fieldConfigs = [];

    public function __construct(Config $config)
    {
        $this->config = $config;
        $this->uploadPath = $config->uploadConfig['uploadPath'] ?? WRITEPATH . 'uploads/';
        $this->thumbnailPath = $config->uploadConfig['thumbnailPath'] ?? WRITEPATH . 'uploads/thumbs/';
    }

    /**
     * Configure upload for a specific field.
     *
     * @param string $field
     * @param array<string, mixed> $config
     */
    public function configureField(string $field, array $config): void
    {
        $this->fieldConfigs[$field] = array_merge($this->config->uploadConfig, $config);
    }

    /**
     * Get configuration for a field.
     */
    public function getFieldConfig(string $field): array
    {
        return $this->fieldConfigs[$field] ?? $this->config->uploadConfig;
    }

    /**
     * Process an uploaded file for a field.
     *
     * @param  UploadedFile|null $file
     * @param  string            $field
     * @return string|null       The saved filename, or null if no file
     * @throws GroceryCrudException
     */
    public function processUpload(?UploadedFile $file, string $field): ?string
    {
        if ($file === null || !$file->isValid() || $file->hasMoved()) {
            return null;
        }

        $fieldConfig = $this->getFieldConfig($field);
        $allowedTypes = $fieldConfig['allowedTypes'] ?? '*';
        $maxSize = ($fieldConfig['maxSize'] ?? 2048) * 1024; // Convert KB to bytes
        $encryptName = $fieldConfig['encryptFileName'] ?? true;

        // Get extension BEFORE file is moved (getExtension triggers finfo on temp path)
        $ext = $file->getExtension();

        // Validate file type
        if ($allowedTypes !== '*') {
            $allowed = explode('|', $allowedTypes);
            if (!in_array(strtolower($ext), $allowed, true)) {
                throw GroceryCrudException::uploadFailed($field, 'Invalid file type: ' . $ext);
            }
        }

        // Validate file size
        if ($file->getSize() > $maxSize) {
            throw GroceryCrudException::uploadFailed($field, 'File size exceeds limit.');
        }

        // Generate filename
        $newName = $encryptName
            ? $file->getRandomName()
            : $file->getName();

        // Ensure upload directory exists
        $uploadDir = $this->uploadPath . $field . '/';
        $this->ensureDirectory($uploadDir);

        // Move file
        $file->move($uploadDir, $newName);

        // Generate thumbnail for images
        if ($this->isImage($ext) && isset($fieldConfig['thumbnailWidth'])) {
            $this->generateThumbnail(
                $uploadDir . $newName,
                $field,
                $fieldConfig['thumbnailWidth'],
                $fieldConfig['thumbnailHeight'] ?? 150
            );
        }

        return $newName;
    }

    /**
     * Delete an uploaded file.
     */
    public function deleteFile(?string $filename, string $field): bool
    {
        if (empty($filename)) {
            return false;
        }

        $filePath = $this->uploadPath . $field . '/' . $filename;
        if (is_file($filePath)) {
            unlink($filePath);
        }

        // Delete thumbnail
        $thumbPath = $this->thumbnailPath . $field . '/' . $filename;
        if (is_file($thumbPath)) {
            unlink($thumbPath);
        }

        return true;
    }

    /**
     * Check if a file extension is an image.
     */
    private function isImage(string $ext): bool
    {
        return in_array(strtolower($ext), ['jpg', 'jpeg', 'png', 'gif', 'webp', 'bmp'], true);
    }

    /**
     * Generate a thumbnail for an image.
     */
    private function generateThumbnail(string $sourcePath, string $field, int $width, int $height): void
    {
        if (!extension_loaded('gd')) {
            return; // GD not available, skip thumbnail
        }

        $thumbDir = $this->thumbnailPath . $field . '/';
        $this->ensureDirectory($thumbDir);

        $filename = basename($sourcePath);
        $destPath = $thumbDir . $filename;

        $image = \Config\Services::image()
            ->withFile($sourcePath)
            ->fit($width, $height, 'center')
            ->save($destPath);
    }

    /**
     * Ensure a directory exists.
     */
    private function ensureDirectory(string $path): void
    {
        if (!is_dir($path)) {
            mkdir($path, 0755, true);
        }
    }

    /**
     * Get the URL path for a field's upload.
     */
    public function getUploadUrl(string $field, ?string $filename): string
    {
        if (empty($filename)) {
            return '';
        }
        return base_url('uploads/' . $field . '/' . $filename);
    }

    /**
     * Get the URL path for a field's thumbnail.
     */
    public function getThumbnailUrl(string $field, ?string $filename): string
    {
        if (empty($filename)) {
            return '';
        }
        $thumbPath = $this->thumbnailPath . $field . '/' . $filename;
        if (is_file($thumbPath)) {
            return base_url('uploads/thumbs/' . $field . '/' . $filename);
        }
        return $this->getUploadUrl($field, $filename);
    }
}
