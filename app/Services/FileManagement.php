<?php

namespace App\Services;

use App\Models\Files;
use Illuminate\Filesystem\FilesystemAdapter;
use SplFileInfo;

/**
 * @package App\Services
 */
class FileManagement
{
    /**
     * @var FilesystemAdapter
     */
    private $disk;


    /**
     * @param FilesystemAdapter $disk
     */
    public function __construct(FilesystemAdapter $disk)
    {
        $this->disk = $disk;
    }


    public function create(SplFileInfo $file, int $shopId, int $updateId, string $group,
        string $fileName, string $type = Files::STAT_FILE_TYPE)
    {
        if (!$this->disk->put($group.$fileName, '')) {
            throw new \Exception('Can\'t save file on disk.', 500);
        }

        $file = new Files();
        $file->shop_id = $shopId;
        $file->update_id = $updateId;
        $file->path = $group.$fileName;
        $file->type = $type;
        $file->save();

        return $file->id;
    }


    public function download(int $id, int $shopId)
    {
        $file = Files::find($id);
        if (empty($file)) {
            throw new \Exception('File not found.', 404);
        }
        if ($file['shop_id'] != $shopId) {
            throw new \Exception('Access denied.', 403);
        }

        return $this->disk->download($file['path']);
    }

    public function getPath(int $id)
    {
        $file = Files::find($id);
        if (empty($file)) {
            throw new \Exception('File not found.', 404);
        }

        return $this->disk->path($file->path);
    }

}
