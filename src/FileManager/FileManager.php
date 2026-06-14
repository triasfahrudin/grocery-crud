<?php

declare(strict_types=1);

namespace GroceryCrud\FileManager;

use CodeIgniter\HTTP\Files\UploadedFile;
use GroceryCrud\Config\Config;
use GroceryCrud\Exceptions\GroceryCrudException;

class FileManager
{
    private Config $config;
    private string $basePath;
    private array $allowedTypes;
    private int $maxSize;

    /** @var string Wildcard untuk mengizinkan semua tipe file */
    private const ALLOW_ALL = '*';

    /** @var array<string, string> Mapping ekstensi ke ikon CSS */
    private const ICON_MAP = [
        'folder'             => 'bi-folder',
        'folder-open'        => 'bi-folder2-open',
        'image'              => 'bi-file-image',
        'pdf'                => 'bi-file-pdf',
        'word'               => 'bi-file-word',
        'excel'              => 'bi-file-excel',
        'archive'            => 'bi-file-zip',
        'audio'              => 'bi-file-music',
        'video'              => 'bi-file-play',
        'code'               => 'bi-file-code',
        'text'               => 'bi-file-text',
        'default'            => 'bi-file-earmark',
    ];

    /** @var array<string, string> Ekstensi file ke kategori ikon */
    private const EXT_TO_CATEGORY = [
        'jpg'  => 'image',
        'jpeg' => 'image',
        'png'  => 'image',
        'gif'  => 'image',
        'webp' => 'image',
        'svg'  => 'image',
        'bmp'  => 'image',
        'ico'  => 'image',
        'pdf'  => 'pdf',
        'doc'  => 'word',
        'docx' => 'word',
        'xls'  => 'excel',
        'xlsx' => 'excel',
        'csv'  => 'excel',
        'zip'  => 'archive',
        'rar'  => 'archive',
        '7z'   => 'archive',
        'tar'  => 'archive',
        'gz'   => 'archive',
        'mp3'  => 'audio',
        'wav'  => 'audio',
        'ogg'  => 'audio',
        'mp4'  => 'video',
        'avi'  => 'video',
        'mkv'  => 'video',
        'mov'  => 'video',
        'php'  => 'code',
        'js'   => 'code',
        'ts'   => 'code',
        'css'  => 'code',
        'html' => 'code',
        'json' => 'code',
        'xml'  => 'code',
        'sql'  => 'code',
        'txt'  => 'text',
        'md'   => 'text',
        'log'  => 'text',
        'env'  => 'text',
        'yml'  => 'text',
        'yaml' => 'text',
    ];

    public function __construct(Config $config)
    {
        $this->config = $config;

        $fmConfig = $config->fileManagerConfig;

        $this->basePath = rtrim($fmConfig['basePath'] ?? FCPATH . 'uploads/', '/');
        $this->allowedTypes = $this->normalizeAllowedTypes($fmConfig['allowedTypes'] ?? self::ALLOW_ALL);
        $this->maxSize = ($fmConfig['maxSize'] ?? 10240) * 1024; // KB → bytes
    }

    /**
     * Normalisasi allowedTypes: string pipe-separated atau array → selalu array.
     *
     * @param string|array $types
     * @return array
     */
    private function normalizeAllowedTypes(string|array $types): array
    {
        if (is_array($types)) {
            return $types;
        }

        if ($types === self::ALLOW_ALL || $types === '') {
            return [self::ALLOW_ALL];
        }

        return explode('|', $types);
    }

    /**
     * Mendapatkan base path yang sudah di-resolve.
     */
    public function getBasePath(): string
    {
        return $this->basePath;
    }

    /**
     * Mendaftar file dan folder dalam sebuah direktori.
     *
     * @param string $subPath Sub-path relatif terhadap basePath
     * @return array<string, mixed>
     */
    public function listFiles(string $subPath = ''): array
    {
        $fullPath = $this->resolvePath($subPath);

        if (!is_dir($fullPath)) {
            throw GroceryCrudException::displayError('Directory not found: ' . $subPath);
        }

        $items = scandir($fullPath);
        $folders = [];
        $files = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            $relativePath = $subPath !== '' ? $subPath . '/' . $item : $item;
            $isDir = is_dir($itemPath);

            $info = [
                'name'       => $item,
                'path'       => $relativePath,
                'isDir'      => $isDir,
                'size'       => $isDir ? 0 : filesize($itemPath),
                'sizeHuman'  => $isDir ? '-' : $this->formatBytes(filesize($itemPath)),
                'modified'   => date('Y-m-d H:i:s', filemtime($itemPath)),
                'icon'       => $isDir
                    ? self::ICON_MAP['folder']
                    : $this->getFileIcon($item),
                'ext'        => $isDir ? '' : strtolower(pathinfo($item, PATHINFO_EXTENSION)),
                'isImage'    => $this->isImage($item),
                'url'        => $isDir ? '' : $this->getFileUrl($relativePath),
                'writable'   => is_writable($itemPath),
            ];

            if ($isDir) {
                $folders[] = $info;
            } else {
                $files[] = $info;
            }
        }

        // Urut: folder dulu (alfabetis), lalu file (alfabetis)
        usort($folders, fn($a, $b) => strcasecmp($a['name'], $b['name']));
        usort($files, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return [
            'success'     => true,
            'folders'     => $folders,
            'files'       => $files,
            'currentPath' => $subPath,
            'parentPath'  => $this->getParentPath($subPath),
            'breadcrumb'  => $this->getBreadcrumb($subPath),
            'writable'    => is_writable($fullPath),
        ];
    }

    /**
     * Mendapatkan struktur direktori dalam bentuk tree untuk sidebar.
     *
     * @return array<int, array<string, mixed>>
     */
    public function getDirectoryTree(): array
    {
        $tree = $this->buildTree($this->basePath, '');
        return $tree;
    }

    /**
     * Build recursive directory tree.
     *
     * @param string $fullPath
     * @param string $relativePath
     * @param int    $depth
     * @return array<int, array<string, mixed>>
     */
    private function buildTree(string $fullPath, string $relativePath, int $depth = 0): array
    {
        if ($depth > 5) {
            return []; // Batasi kedalaman untuk performa
        }

        $items = scandir($fullPath);
        $result = [];

        foreach ($items as $item) {
            if ($item === '.' || $item === '..') {
                continue;
            }

            $itemPath = $fullPath . '/' . $item;
            if (!is_dir($itemPath)) {
                continue;
            }

            $subRelative = $relativePath !== '' ? $relativePath . '/' . $item : $item;

            $entry = [
                'name'     => $item,
                'path'     => $subRelative,
                'children' => $this->buildTree($itemPath, $subRelative, $depth + 1),
            ];

            $result[] = $entry;
        }

        // Urut alfabetis
        usort($result, fn($a, $b) => strcasecmp($a['name'], $b['name']));

        return $result;
    }

    /**
     * Membuat folder baru.
     *
     * @param string $parentPath Path parent relatif
     * @param string $folderName Nama folder
     * @return bool
     */
    public function createFolder(string $parentPath, string $folderName): bool
    {
        $folderName = $this->sanitizeFilename($folderName);

        if (empty($folderName)) {
            throw GroceryCrudException::displayError('Invalid folder name.');
        }

        $fullPath = $this->resolvePath($parentPath) . '/' . $folderName;

        if (is_dir($fullPath)) {
            throw GroceryCrudException::displayError('Folder already exists.');
        }

        if (!mkdir($fullPath, 0755, true) && !is_dir($fullPath)) {
            throw GroceryCrudException::displayError('Failed to create folder.');
        }

        return true;
    }

    /**
     * Rename file atau folder.
     *
     * @param string $oldPath Path relatif lama
     * @param string $newName Nama baru
     * @return bool
     */
    public function rename(string $oldPath, string $newName): bool
    {
        $newName = $this->sanitizeFilename($newName);

        if (empty($newName)) {
            throw GroceryCrudException::displayError('Invalid name.');
        }

        $fullOldPath = $this->resolvePath($oldPath);

        if (!file_exists($fullOldPath)) {
            throw GroceryCrudException::displayError('File or folder not found.');
        }

        $parentDir = dirname($fullOldPath);
        $fullNewPath = $parentDir . '/' . $newName;

        if (file_exists($fullNewPath)) {
            throw GroceryCrudException::displayError('A file or folder with that name already exists.');
        }

        if (!rename($fullOldPath, $fullNewPath)) {
            throw GroceryCrudException::displayError('Failed to rename.');
        }

        return true;
    }

    /**
     * Menghapus file atau folder.
     *
     * @param string $path Path relatif
     * @return bool
     */
    public function delete(string $path): bool
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            throw GroceryCrudException::displayError('File or folder not found.');
        }

        if (is_dir($fullPath)) {
            $this->deleteDirectory($fullPath);
        } else {
            if (!unlink($fullPath)) {
                throw GroceryCrudException::displayError('Failed to delete file.');
            }
        }

        return true;
    }

    /**
     * Upload file ke direktori tujuan.
     *
     * @param string        $targetPath Path relatif direktori tujuan
     * @param UploadedFile  $file       File yang diupload
     * @return string Nama file yang disimpan
     */
    public function upload(string $targetPath, UploadedFile $file): string
    {
        if (!$file->isValid() || $file->hasMoved()) {
            throw GroceryCrudException::uploadFailed('file', 'Invalid file upload.');
        }

        // Validasi tipe file
        $ext = strtolower($file->getExtension());
        if (!in_array(self::ALLOW_ALL, $this->allowedTypes, true)) {
            if (!in_array($ext, $this->allowedTypes, true)) {
                throw GroceryCrudException::uploadFailed('file', 'File type not allowed: ' . $ext);
            }
        }

        // Validasi ukuran
        if ($file->getSize() > $this->maxSize) {
            throw GroceryCrudException::uploadFailed('file', 'File size exceeds limit.');
        }

        $uploadDir = $this->resolvePath($targetPath);

        if (!is_dir($uploadDir)) {
            throw GroceryCrudException::displayError('Target directory not found.');
        }

        if (!is_writable($uploadDir)) {
            throw GroceryCrudException::displayError('Target directory is not writable.');
        }

        $newName = $file->getRandomName();
        $file->move($uploadDir, $newName);

        return $newName;
    }

    /**
     * Copy file atau folder.
     *
     * @param string $sourcePath      Path relatif sumber
     * @param string $destDirPath     Path relatif direktori tujuan
     * @return bool
     */
    public function copy(string $sourcePath, string $destDirPath): bool
    {
        $fullSource = $this->resolvePath($sourcePath);
        $fullDestDir = $this->resolvePath($destDirPath);

        if (!file_exists($fullSource)) {
            throw GroceryCrudException::displayError('Source not found.');
        }

        if (!is_dir($fullDestDir)) {
            throw GroceryCrudException::displayError('Destination directory not found.');
        }

        if (!is_writable($fullDestDir)) {
            throw GroceryCrudException::displayError('Destination directory is not writable.');
        }

        $basename = basename($fullSource);
        $destPath = $fullDestDir . '/' . $basename;

        if (file_exists($destPath)) {
            throw GroceryCrudException::displayError('A file with that name already exists in the destination.');
        }

        if (is_dir($fullSource)) {
            $this->copyDirectory($fullSource, $destPath);
        } else {
            if (!copy($fullSource, $destPath)) {
                throw GroceryCrudException::displayError('Failed to copy file.');
            }
        }

        return true;
    }

    /**
     * Move file atau folder.
     *
     * @param string $sourcePath  Path relatif sumber
     * @param string $destDirPath Path relatif direktori tujuan
     * @return bool
     */
    public function move(string $sourcePath, string $destDirPath): bool
    {
        $fullSource = $this->resolvePath($sourcePath);
        $fullDestDir = $this->resolvePath($destDirPath);

        if (!file_exists($fullSource)) {
            throw GroceryCrudException::displayError('Source not found.');
        }

        if (!is_dir($fullDestDir)) {
            throw GroceryCrudException::displayError('Destination directory not found.');
        }

        if (!is_writable($fullDestDir)) {
            throw GroceryCrudException::displayError('Destination directory is not writable.');
        }

        $basename = basename($fullSource);
        $destPath = $fullDestDir . '/' . $basename;

        if (file_exists($destPath)) {
            throw GroceryCrudException::displayError('A file with that name already exists in the destination.');
        }

        if (!rename($fullSource, $destPath)) {
            throw GroceryCrudException::displayError('Failed to move file.');
        }

        return true;
    }

    /**
     * Mendapatkan info detail sebuah file/folder.
     *
     * @param string $path Path relatif
     * @return array<string, mixed>|null
     */
    public function getFileInfo(string $path): ?array
    {
        $fullPath = $this->resolvePath($path);

        if (!file_exists($fullPath)) {
            return null;
        }

        $isDir = is_dir($fullPath);
        $ext = $isDir ? '' : strtolower(pathinfo($fullPath, PATHINFO_EXTENSION));

        return [
            'name'       => basename($fullPath),
            'path'       => $path,
            'isDir'      => $isDir,
            'size'       => $isDir ? 0 : filesize($fullPath),
            'sizeHuman'  => $isDir ? '-' : $this->formatBytes(filesize($fullPath)),
            'modified'   => date('Y-m-d H:i:s', filemtime($fullPath)),
            'created'    => date('Y-m-d H:i:s', filectime($fullPath)),
            'permissions'=> substr(sprintf('%o', fileperms($fullPath)), -4),
            'icon'       => $isDir ? self::ICON_MAP['folder'] : $this->getFileIcon(basename($fullPath)),
            'ext'        => $ext,
            'isImage'    => $this->isImage(basename($fullPath)),
            'url'        => $isDir ? '' : $this->getFileUrl($path),
            'writable'   => is_writable($fullPath),
        ];
    }

    /**
     * Pencarian file.
     *
     * @param string $query  Kata kunci pencarian
     * @param string $subPath Sub-path awal
     * @return array<int, array<string, mixed>>
     */
    public function search(string $query, string $subPath = ''): array
    {
        $fullPath = $this->resolvePath($subPath);
        $results = [];

        if (empty(trim($query))) {
            return $results;
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($fullPath, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        $iterator->setMaxDepth(3); // Batasi kedalaman

        foreach ($iterator as $item) {
            $relativePath = $this->getRelativePath($item->getPathname());
            $name = $item->getFilename();

            // Skip hidden files
            if (str_starts_with($name, '.')) {
                continue;
            }

            if (stripos($name, $query) !== false) {
                $isDir = $item->isDir();
                $results[] = [
                    'name'      => $name,
                    'path'      => $relativePath,
                    'isDir'     => $isDir,
                    'sizeHuman' => $isDir ? '-' : $this->formatBytes($item->getSize()),
                    'modified'  => date('Y-m-d H:i:s', $item->getMTime()),
                    'icon'      => $isDir ? self::ICON_MAP['folder'] : $this->getFileIcon($name),
                ];
            }
        }

        // Batasi hasil
        return array_slice($results, 0, 50);
    }

    // ======== Private Helpers ========

    /**
     * Me-resolve path relatif ke path absolut dengan keamanan path traversal.
     */
    private function resolvePath(string $subPath): string
    {
        // Normalisasi
        $subPath = str_replace('\\', '/', $subPath);
        $subPath = trim($subPath, '/');

        // Cegah path traversal
        if (str_contains($subPath, '..')) {
            throw GroceryCrudException::displayError('Invalid path.');
        }

        $fullPath = $subPath !== ''
            ? $this->basePath . '/' . $subPath
            : $this->basePath;

        // Pastikan masih di dalam basePath
        $realBase = realpath($this->basePath);
        $realFull = realpath($fullPath);

        if ($realFull === false || !str_starts_with($realFull, $realBase)) {
            throw GroceryCrudException::displayError('Access denied.');
        }

        return $realFull;
    }

    /**
     * Mendapatkan path relatif dari path absolut.
     */
    private function getRelativePath(string $absolutePath): string
    {
        $prefix = rtrim($this->basePath, '/') . '/';
        if (str_starts_with($absolutePath, $prefix)) {
            return substr($absolutePath, strlen($prefix));
        }
        return basename($absolutePath);
    }

    /**
     * Mendapatkan path parent.
     */
    private function getParentPath(string $subPath): ?string
    {
        if (empty($subPath)) {
            return null;
        }

        $parent = dirname($subPath);
        return $parent === '.' ? null : $parent;
    }

    /**
     * Mendapatkan breadcrumb array.
     *
     * @return array<int, array{name: string, path: string}>
     */
    private function getBreadcrumb(string $subPath): array
    {
        $parts = $subPath !== '' ? explode('/', $subPath) : [];
        $crumbs = [
            ['name' => 'Root', 'path' => ''],
        ];

        $current = '';
        foreach ($parts as $part) {
            $current = $current !== '' ? $current . '/' . $part : $part;
            $crumbs[] = ['name' => $part, 'path' => $current];
        }

        return $crumbs;
    }

    /**
     * Sanitasi nama file/folder.
     */
    private function sanitizeFilename(string $name): string
    {
        // Hapus karakter path traversal dan berbahaya
        $name = str_replace(['..', '/', '\\', "\0"], '', $name);
        $name = trim($name);

        // Hapus karakter non-ASCII dan kontrol
        $name = preg_replace('/[^\w\.\-\(\)\[\]\s]/u', '', $name);
        $name = trim($name);

        return $name;
    }

    /**
     * Format bytes ke human-readable.
     */
    private function formatBytes(int $bytes, int $precision = 2): string
    {
        $units = ['B', 'KB', 'MB', 'GB', 'TB'];
        $bytes = max(0, $bytes);
        $pow = floor(($bytes ? log($bytes) : 0) / log(1024));
        $pow = min($pow, count($units) - 1);
        $bytes /= pow(1024, $pow);

        return round($bytes, $precision) . ' ' . $units[$pow];
    }

    /**
     * Mendapatkan ikon untuk file berdasarkan ekstensi.
     */
    private function getFileIcon(string $filename): string
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        $category = self::EXT_TO_CATEGORY[$ext] ?? 'default';
        return self::ICON_MAP[$category] ?? self::ICON_MAP['default'];
    }

    /**
     * Memeriksa apakah file adalah gambar.
     */
    private function isImage(string $filename): bool
    {
        $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
        return in_array($ext, ['jpg', 'jpeg', 'png', 'gif', 'webp', 'svg', 'bmp', 'ico'], true);
    }

    /**
     * Mendapatkan URL publik untuk file.
     */
    private function getFileUrl(string $path): string
    {
        $uploadUrl = $this->config->fileManagerConfig['baseUrl'] ?? '';

        if (empty($uploadUrl)) {
            // Coba deteksi dari basePath: path setelah FCPATH
            $basePath = rtrim($this->config->fileManagerConfig['basePath'] ?? 'uploads/', '/');
            $uploadUrl = base_url($basePath);
        }

        return rtrim($uploadUrl, '/') . '/' . $path;
    }

    /**
     * Hapus direktori beserta isinya secara rekursif.
     */
    private function deleteDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                rmdir($item->getPathname());
            } else {
                unlink($item->getPathname());
            }
        }

        rmdir($dir);
    }

    /**
     * Copy direktori beserta isinya secara rekursif.
     */
    private function copyDirectory(string $source, string $dest): void
    {
        if (!is_dir($dest)) {
            mkdir($dest, 0755, true);
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($source, \RecursiveDirectoryIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::SELF_FIRST
        );

        foreach ($items as $item) {
            $destPath = $dest . '/' . $items->getSubPathname();

            if ($item->isDir()) {
                if (!is_dir($destPath)) {
                    mkdir($destPath, 0755, true);
                }
            } else {
                copy($item->getPathname(), $destPath);
            }
        }
    }
}
