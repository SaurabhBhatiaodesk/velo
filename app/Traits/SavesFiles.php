<?php

namespace App\Traits;


use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\File\File;

trait SavesFiles
{
    /**
     * Get mime type from base64 string
     * @param string $file64
     * @return string
     */
    public function mimeFromBase64($file64)
    {
        $file64 = explode(':', $file64)[1];
        return explode(';', $file64)[0];
    }

    /**
     * Convert base64 string to UploadedFile
     * @param string $fileContent
     * @return UploadedFile | null
     */
    public function stringToUploadedFile($fileContent)
    {
        if (is_string($fileContent)) {
            $tmpFilePath = sys_get_temp_dir() . '/' . Str::uuid()->toString();
            $mime = $this->mimeFromBase64($fileContent);

            if (isset(config('excel.mime_to_ext')[$mime])) {
                $tmpFilePath .= '.' . config('excel.mime_to_ext')[$mime];
            }
            file_put_contents($tmpFilePath, $this->decodeFileIfEncoded($fileContent));

            // this just to help us get file info.
            $tmpFile = new File($tmpFilePath);

            $file = new UploadedFile(
                $tmpFile->getPathname(),
                $tmpFile->getFilename(),
                // $tmpFile->getFilename().'.'.$tmpFile->getExtension(),
                $tmpFile->getMimeType(),
                null,
                false // Mark it as test, since the file isn't from real HTTP POST.
            );
            return $file;
        }
        return null;
    }

    /**
     * Decode file if encoded
     * @param mixed $file
     * @return string
     */
    public function decodeFileIfEncoded($file)
    {
        if (is_string($file) && str_starts_with($file, 'data:')) {
            if (strpos($file, ',') !== false) {
                $file = explode(',', $file);
                unset($file[0]);
                $file = implode(',', $file);
            }
            $file = base64_decode($file);
        }
        return $file;
    }

    /**
     * Create directory structure for file path
     * @param string $path
     * @param string $disk
     * @return array
     */
    public function createPathDirectoryStructure($path, $disk = 'public')
    {
        $pathPieces = explode('/', $path);
        $currentPath = '';
        foreach ($pathPieces as $pathPiece) {
            if (strpos($pathPiece, '.')) {
                return [
                    'path' => $currentPath,
                    'filename' => $pathPiece,
                ];
            } else {
                $currentPath .= $pathPiece;
                if (!Storage::disk($disk)->exists($currentPath)) {
                    if (!Storage::disk($disk)->makeDirectory($currentPath, 0777, true, true)) {
                        return [
                            'fail' => true,
                            'error' => 'filesystem.cantCreateDirectoryStucture',
                            'data' => [$currentPath],
                            'code' => 500,
                        ];
                    }
                }
            }
        }
        return [
            'fail' => true,
            'error' => 'filesystem.invalidPath',
            'data' => [$currentPath],
            'code' => 500,
        ];
    }

    /**
     * Save file to storage
     * @param string $path
     * @param string $file
     * @param string $disk
     * @return mixed
     */
    public function saveFile($path, $file, $disk = 'public')
    {
        $path = $this->createPathDirectoryStructure($path, $disk);
        $file = $this->stringToUploadedFile($file);
        if (is_null($file)) {
            return [
                'fail' => true,
                'error' => 'filesystem.decodingFailed',
                'data' => [$path],
                'code' => 500,
            ];
        }
        if (!Storage::disk($disk)->putFileAs($path['path'], $file, $path['filename'], 'public')) {
            return [
                'fail' => true,
                'error' => 'filesystem.saveFailed',
                'data' => [$path],
                'code' => 500,
            ];
        }
        if ($disk === 'public') {
            return config('app.url') . '/storage/' . $path['path'] . '/' . $path['filename'];
        }
        return $file;
    }
}
