<?php

namespace App\Services;

use Illuminate\Http\UploadedFile;

class ImageService
{
    public function processAvatar(UploadedFile $file, ?int $userId = null): string
    {
        $realPath = $file->getRealPath();
        $mime = $file->getMimeType();

        if (! function_exists('imagecreatetruecolor')) {
            return $this->storeOriginalAvatar($file, $userId);
        }

        switch ($mime) {
            case 'image/jpeg':
            case 'image/jpg':
                if (! function_exists('imagecreatefromjpeg')) {
                    return $this->storeOriginalAvatar($file, $userId);
                }

                $sourceImage = imagecreatefromjpeg($realPath);
                break;

            case 'image/png':
                if (! function_exists('imagecreatefrompng')) {
                    return $this->storeOriginalAvatar($file, $userId);
                }

                $sourceImage = imagecreatefrompng($realPath);
                break;

            case 'image/webp':
                if (! function_exists('imagecreatefromwebp')) {
                    return $this->storeOriginalAvatar($file, $userId);
                }

                $sourceImage = imagecreatefromwebp($realPath);
                break;

            default:
                throw new \Exception('Unsupported image type.');
        }

        if (! $sourceImage) {
            throw new \Exception('Failed to read uploaded image.');
        }

        $srcWidth = imagesx($sourceImage);
        $srcHeight = imagesy($sourceImage);

        $targetSize = 300;
        $srcRatio = $srcWidth / $srcHeight;

        if ($srcRatio > 1) {
            $cropWidth = $srcHeight;
            $cropHeight = $srcHeight;
            $srcX = (int) (($srcWidth - $cropWidth) / 2);
            $srcY = 0;
        } else {
            $cropWidth = $srcWidth;
            $cropHeight = $srcWidth;
            $srcX = 0;
            $srcY = (int) (($srcHeight - $cropHeight) / 2);
        }

        $finalImage = imagecreatetruecolor($targetSize, $targetSize);

        imagecopyresampled(
            $finalImage,
            $sourceImage,
            0,
            0,
            $srcX,
            $srcY,
            $targetSize,
            $targetSize,
            $cropWidth,
            $cropHeight
        );

        $fileName = 'avatar_'.($userId ?? 'tmp').'_'.time().'_'.uniqid().'.jpg';
        $relativePath = 'avatars/'.$fileName;
        $fullPath = storage_path('app/public/'.$relativePath);

        if (! file_exists(dirname($fullPath))) {
            mkdir(dirname($fullPath), 0755, true);
        }

        imagejpeg($finalImage, $fullPath, 85);

        imagedestroy($sourceImage);
        imagedestroy($finalImage);

        return $relativePath;
    }

    private function storeOriginalAvatar(UploadedFile $file, ?int $userId = null): string
    {
        $extension = $file->guessExtension() ?: $file->extension() ?: 'jpg';
        $fileName = 'avatar_'.($userId ?? 'tmp').'_'.time().'_'.uniqid().'.'.$extension;

        return $file->storeAs('avatars', $fileName, 'public');
    }
}
